<?php

namespace App\Services\Central;

use App\Support\Configuracion\Configuracion;
use Exception;
use Carbon\Carbon;
use App\Models\Central\Academia;
use App\Models\Central\PagoAcademia;
use App\Models\Central\FacturaAcademia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\Central\FacturacionAcademiaMail as Correo;

/**
 * Cobro mensual del ERP a sus academias clientes.
 *
 * - Cuenta de cobro: el día de corte de cada academia por su tarifa mensual; vence
 *   'dias_plazo' después. No se cobra antes de fecha_inicio_cobro (sirve de periodo de prueba).
 * - Avisos: cuenta nueva, recordatorio antes del vencimiento y mora cada N días.
 * - Suspensión: pasados 'dias_gracia' desde el vencimiento sin pago. Al quedar sin cuentas
 *   vencidas (registrando el pago) se reactiva sola.
 * No se factura a la academia administradora ni a las suspendidas a mano (activa=0 sin mora).
 */
class FacturacionAcademias
{
    public function __construct(private Carbon $hoy, private bool $simular = false)
    {
        $this->hoy = $hoy->copy()->startOfDay();
    }

    // Parámetros editables en la academia administradora (Configuración → Parámetros).
    private const PARAMETROS = [
        'tarifa_base' => 'ERP_TARIFA_MENSUAL',
        'dias_plazo' => 'ERP_DIAS_PLAZO',
        'dias_recordatorio' => 'ERP_DIAS_RECORDATORIO',
        'dias_gracia' => 'ERP_DIAS_GRACIA',
        'dias_entre_avisos_mora' => 'ERP_DIAS_ENTRE_AVISOS_MORA',
        'datos_pago' => 'ERP_DATOS_PAGO',
        'contacto' => 'ERP_CORREO_CONTACTO',
    ];

    /** Configuración del cobro del ERP: parámetro de la academia administradora, o el .env por defecto. */
    public static function cfg(string $clave)
    {
        $defecto = config('academias.facturacion.' . $clave);
        if (!isset(self::PARAMETROS[$clave])) {
            return $defecto;
        }
        $valor = Configuracion::parametroAdministradora(self::PARAMETROS[$clave], $defecto);
        return is_numeric($defecto) && is_numeric($valor) ? $valor + 0 : $valor;
    }

    public static function corteDelMes(Academia $academia, Carbon $fecha): Carbon
    {
        $dia = min(max($academia->dia_corte, 1), $fecha->daysInMonth);
        return Carbon::create($fecha->year, $fecha->month, $dia)->startOfDay();
    }

    /** Academias a las que se les factura. */
    public static function facturables()
    {
        return Academia::where('es_administradora', false)
            ->where(fn ($q) => $q->where('activa', true)->orWhere('suspendida_por_mora', true))
            ->orderBy('id');
    }

    public function ejecutar(?string $codigo = null): array
    {
        $filas = [];
        $query = self::facturables();
        if ($codigo) {
            $query->where('codigo', $codigo);
        }
        foreach ($query->get() as $academia) {
            $r = ['academia' => $academia->codigo, 'facturas' => 0, 'avisos' => 0, 'suspendida' => '', 'errores' => 0];
            try {
                $this->procesar($academia, $r);
            } catch (Exception $e) {
                $r['errores']++;
                Log::error("Facturación {$academia->codigo}: " . $e->getMessage());
            }
            $filas[] = $r;
        }
        return $filas;
    }

    private function procesar(Academia $academia, array &$r): void
    {
        // 1. Cuenta de cobro del mes.
        $corte = self::corteDelMes($academia, $this->hoy);
        $inicio = $academia->fecha_inicio_cobro ?? $academia->created_at->copy()->startOfDay();
        if ($academia->tarifa_mensual > 0 && $this->hoy->gte($corte) && $corte->gte($inicio)) {
            $existe = FacturaAcademia::where('academia_id', $academia->id)->whereDate('periodo', $corte)->exists();
            if (!$existe) {
                $r['facturas']++;
                if (!$this->simular) {
                    $factura = FacturaAcademia::create([
                        'academia_id' => $academia->id,
                        'periodo' => $corte,
                        'fecha_vencimiento' => $corte->copy()->addDays(self::cfg('dias_plazo')),
                        'valor' => $academia->tarifa_mensual,
                        'saldo' => $academia->tarifa_mensual,
                        'estado' => FacturaAcademia::PENDIENTE,
                    ]);
                    $this->notificar($academia, Correo::FACTURA, $factura, $r);
                }
            }
        }

        $pendientes = FacturaAcademia::where('academia_id', $academia->id)
            ->where('estado', FacturaAcademia::PENDIENTE)
            ->orderBy('fecha_vencimiento')
            ->get();

        foreach ($pendientes as $factura) {
            $diasParaVencer = (int) round($this->hoy->diffInDays($factura->fecha_vencimiento, false));

            // 2. Recordatorio antes del vencimiento (una vez por cuenta).
            if ($diasParaVencer > 0 && $diasParaVencer <= self::cfg('dias_recordatorio')
                && !$this->yaNotificado($academia, Correo::RECORDATORIO, $factura)) {
                $this->notificar($academia, Correo::RECORDATORIO, $factura, $r);
            }
        }

        // 3. Mora y suspensión según la cuenta vencida más antigua.
        $vencida = $pendientes->first(fn ($f) => $this->hoy->gt($f->fecha_vencimiento));
        if (!$vencida) {
            return;
        }
        $diasVencida = (int) round($vencida->fecha_vencimiento->diffInDays($this->hoy));

        if ($diasVencida > self::cfg('dias_gracia')) {
            if ($academia->activa) {
                $r['suspendida'] = 'sí';
                if (!$this->simular) {
                    $academia->update(['activa' => false, 'suspendida_por_mora' => true]);
                    $this->notificar($academia, Correo::SUSPENSION, $vencida, $r);
                }
            }
            return;
        }

        $cadaDias = self::cfg('dias_entre_avisos_mora');
        $desde = $this->hoy->copy()->subDays($cadaDias - 1);
        if (!$this->yaNotificado($academia, Correo::MORA, null, $desde)) {
            $this->notificar($academia, Correo::MORA, $vencida, $r);
        }
    }

    private function yaNotificado(Academia $academia, string $tipo, ?FacturaAcademia $factura, ?Carbon $desde = null): bool
    {
        $q = DB::connection('central')->table('notificaciones_academia')
            ->where('academia_id', $academia->id)
            ->where('tipo', $tipo)
            ->whereIn('estado', ['enviado', 'sin_correo']);
        if ($factura) {
            $q->where('factura_id', $factura->id);
        }
        if ($desde) {
            $q->where('fecha', '>=', $desde->toDateString());
        }
        return $q->exists();
    }

    private function notificar(Academia $academia, string $tipo, FacturaAcademia $factura, array &$r): void
    {
        $r['avisos']++;
        if ($this->simular) {
            return;
        }
        self::enviar($academia, $tipo, $factura, $this->hoy);
    }

    /** Envía el correo al contacto de la academia y lo deja en la bitácora. */
    public static function enviar(Academia $academia, string $tipo, FacturaAcademia $factura, ?Carbon $fecha = null, ?PagoAcademia $pago = null): void
    {
        $estado = 'enviado';
        $error = null;
        if (!$academia->correo) {
            $estado = 'sin_correo';
        } else {
            try {
                Mail::to($academia->correo)->send(new Correo($tipo, self::datosCorreo($academia, $factura, $pago)));
            } catch (Exception $e) {
                $estado = 'error';
                $error = $e->getMessage();
                Log::error("Correo de facturación {$tipo} a {$academia->correo}: {$error}");
            }
        }

        DB::connection('central')->table('notificaciones_academia')->insert([
            'academia_id' => $academia->id,
            'factura_id' => $factura->id,
            'tipo' => $tipo,
            'fecha' => ($fecha ?? Carbon::today())->toDateString(),
            'correo' => $academia->correo,
            'estado' => $estado,
            'error' => $error,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private static function datosCorreo(Academia $academia, FacturaAcademia $factura, ?PagoAcademia $pago): array
    {
        $saldoTotal = (float) FacturaAcademia::where('academia_id', $academia->id)
            ->where('estado', FacturaAcademia::PENDIENTE)
            ->sum('saldo');

        return [
            'marca' => self::cfg('marca'),
            'contacto' => self::cfg('contacto'),
            'datos_pago' => self::cfg('datos_pago'),
            'dias_gracia' => self::cfg('dias_gracia'),
            'academia' => $academia->nombre,
            'numero' => $factura->numero(),
            'periodo' => $factura->periodo->copy()->locale('es')->translatedFormat('F Y'),
            'valor' => $factura->valor,
            'saldo' => $factura->saldo,
            'saldo_total' => $saldoTotal,
            'vencimiento' => $factura->fecha_vencimiento->format('d/m/Y'),
            'fecha_suspension' => $factura->fecha_vencimiento->copy()->addDays(self::cfg('dias_gracia') + 1)->format('d/m/Y'),
            'pago_valor' => $pago?->valor,
            'pago_fecha' => $pago?->fecha_pago?->format('d/m/Y'),
            'url' => $academia->url(),
        ];
    }

    /**
     * Registra un pago contra una cuenta de cobro. Si la academia estaba suspendida por mora
     * y ya no tiene cuentas vencidas, se reactiva.
     */
    public static function registrarPago(array $datos): PagoAcademia
    {
        return DB::connection('central')->transaction(function () use ($datos) {
            $factura = FacturaAcademia::lockForUpdate()->findOrFail($datos['factura_id']);
            if ($factura->estado === FacturaAcademia::ANULADA) {
                throw new Exception('La cuenta de cobro está anulada.');
            }
            if ($datos['valor'] > $factura->saldo + 0.009) {
                throw new Exception('El valor supera el saldo de la cuenta de cobro ($' . number_format($factura->saldo, 0, ',', '.') . ').');
            }

            $pago = PagoAcademia::create(array_merge($datos, ['academia_id' => $factura->academia_id]));

            $factura->saldo = round($factura->saldo - $pago->valor, 2);
            $factura->estado = $factura->saldo <= 0 ? FacturaAcademia::PAGADA : FacturaAcademia::PENDIENTE;
            $factura->save();

            $academia = $factura->academia;
            $reactivada = self::reactivarSiCorresponde($academia);

            DB::connection('central')->afterCommit(function () use ($academia, $factura, $pago) {
                self::enviar($academia, Correo::PAGO, $factura, null, $pago);
            });

            $pago->reactivada = $reactivada;
            return $pago;
        });
    }

    /** Revierte un pago (registrado por error). No vuelve a suspender: eso lo decide el proceso diario. */
    public static function eliminarPago(PagoAcademia $pago): void
    {
        DB::connection('central')->transaction(function () use ($pago) {
            $factura = FacturaAcademia::lockForUpdate()->find($pago->factura_id);
            if ($factura && $factura->estado !== FacturaAcademia::ANULADA) {
                $factura->saldo = round($factura->saldo + $pago->valor, 2);
                $factura->estado = FacturaAcademia::PENDIENTE;
                $factura->save();
            }
            $pago->delete();
        });
    }

    public static function reactivarSiCorresponde(Academia $academia): bool
    {
        if (!$academia->suspendida_por_mora) {
            return false;
        }
        $tieneVencidas = FacturaAcademia::where('academia_id', $academia->id)
            ->where('estado', FacturaAcademia::PENDIENTE)
            ->whereDate('fecha_vencimiento', '<', Carbon::today())
            ->exists();
        if ($tieneVencidas) {
            return false;
        }
        $academia->update(['activa' => true, 'suspendida_por_mora' => false]);
        return true;
    }

    /**
     * Estado de cuenta de una academia: al_dia | pendiente | en_mora | suspendida | sin_cobro.
     */
    public static function estadoCuenta(Academia $academia): array
    {
        if ($academia->es_administradora) {
            return ['estado' => 'sin_cobro', 'saldo' => 0];
        }
        $pendientes = FacturaAcademia::where('academia_id', $academia->id)
            ->where('estado', FacturaAcademia::PENDIENTE)
            ->orderBy('fecha_vencimiento')
            ->get();
        $hoy = Carbon::today();
        $vencida = $pendientes->first(fn ($f) => $hoy->gt($f->fecha_vencimiento));

        $estado = 'al_dia';
        if (!$academia->activa) {
            $estado = 'suspendida';
        } elseif ($vencida) {
            $estado = 'en_mora';
        } elseif ($pendientes->isNotEmpty()) {
            $estado = 'pendiente';
        }

        $proxima = $pendientes->first();
        return [
            'estado' => $estado,
            'saldo' => (float) $pendientes->sum('saldo'),
            'cuentas_pendientes' => $pendientes->count(),
            'proximo_vencimiento' => $proxima?->fecha_vencimiento?->format('Y-m-d'),
            'fecha_suspension' => $vencida
                ? $vencida->fecha_vencimiento->copy()->addDays(self::cfg('dias_gracia') + 1)->format('Y-m-d')
                : null,
        ];
    }
}
