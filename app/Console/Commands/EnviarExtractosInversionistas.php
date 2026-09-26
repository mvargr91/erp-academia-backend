<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Mail\ExtractoInversionistaMail;

class EnviarExtractosInversionistas extends Command
{
    protected $signature = 'extractos:enviar';
    protected $description = 'Genera y envía extractos mensuales a cada inversionista activo';

    public function handle()
    {
        Carbon::setLocale('es'); // ✅ Para mostrar nombre del mes en español

        $inicio = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $fin = Carbon::now()->subMonth()->endOfMonth()->toDateString();
        $hoy = Carbon::now()->toDateString();

        $fechaGeneracion = Carbon::parse($hoy);
        $mesVencimiento = $fechaGeneracion->month;
        $anioVencimiento = $fechaGeneracion->year;
        $nombreMes = mb_strtolower($fechaGeneracion->translatedFormat('F'), 'UTF-8');

        $inversionistas = DB::table('inversionistas')
            ->where('estado', 1)
            ->select('id', 'nombre', 'numero_documento', 'email')
            ->get();

        foreach ($inversionistas as $inversionista) {
            // Inversiones activas
            $inversiones = DB::table('inversiones as i')
                ->join('inversiones_proyectos as ip', 'i.id', '=', 'ip.id_inversion')
                ->whereIn('i.estado_inversion', ['GEN', 'PRG'])
                ->where('i.id_inversionista', $inversionista->id)
                ->select('ip.codigo_proyecto', 'ip.fecha_inversion', 'ip.valor_inversion_por_proyecto')
                ->get();

            // Transacciones del mes pasado
            $transacciones = DB::table('inversiones_plan_detallado as ipd')
                ->join('proyectos as p', 'ipd.id_proyecto', '=', 'p.id')
                ->where('ipd.id_inversionista', $inversionista->id)
                ->whereIn('ipd.tipo_concepto', ['I', 'K'])
                ->whereBetween('ipd.fecha_pago', [$inicio, $fin])
                ->select(
                    'p.codigo_proyecto',
                    'ipd.fecha_pago as fecha',
                    DB::raw("
                        CASE ipd.tipo_concepto
                            WHEN 'K' THEN 'Reintegro Capital' 
                            WHEN 'I' THEN 'Rendimientos' 
                            WHEN 'C' THEN 'Comisión Gestión' 
                            ELSE '' 
                        END AS tipo_concepto
                    "),
                    'ipd.valor_concepto',
                    'ipd.valor_ret_fuente',
                    DB::raw('(ipd.valor_concepto - ipd.valor_ret_fuente) as valor_a_pagar')
                )
                ->get();

            // Rendimientos del mes actual
            $rendimientos = DB::table('inversiones_plan_detallado as ipd')
                ->join('proyectos as p', 'ipd.id_proyecto', '=', 'p.id')
                ->where('ipd.id_inversionista', $inversionista->id)
                ->where('ipd.tipo_concepto', 'I')
                ->whereMonth('ipd.fecha_vencimiento', $mesVencimiento)
                ->whereYear('ipd.fecha_vencimiento', $anioVencimiento)
                ->select(
                    'p.codigo_proyecto',
                    'ipd.fecha_vencimiento',
                    'ipd.valor_concepto',
                    'ipd.valor_ret_fuente',
                    DB::raw('(ipd.valor_concepto - ipd.valor_ret_fuente) as valor_a_pagar')
                )
                ->get();

            // Reintegros del mes actual
            $reintegros = DB::table('inversiones_plan_detallado as ipd')
                ->join('proyectos as p', 'ipd.id_proyecto', '=', 'p.id')
                ->where('ipd.id_inversionista', $inversionista->id)
                ->where('ipd.tipo_concepto', 'K')
                ->whereMonth('ipd.fecha_vencimiento', $mesVencimiento)
                ->whereYear('ipd.fecha_vencimiento', $anioVencimiento)
                ->select('p.codigo_proyecto', 'ipd.fecha_vencimiento', 'ipd.valor_concepto')
                ->get();

            // Generar PDF
            $pdf = Pdf::loadView('pdf.extracto_inversionista', [
                'nombre' => $inversionista->nombre,
                'documento' => $inversionista->numero_documento,
                'email' => $inversionista->email,
                'fechaGeneracion' => $hoy,
                'desde' => $inicio,
                'hasta' => $fin,
                'inversiones' => $inversiones,
                'transacciones' => $transacciones,
                'rendimientos' => $rendimientos,
                'reintegros' => $reintegros,
                'nombreMes' => $nombreMes,
            ])->output();

            Mail::to($inversionista->email)
            ->bcc(env('MAIL_USERNAME'))
            ->send(new ExtractoInversionistaMail($pdf, $inversionista));
        }

        $this->info('Extractos enviados exitosamente.');
    }
}
