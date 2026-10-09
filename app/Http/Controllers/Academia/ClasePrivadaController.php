<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Academia\Pago;
use App\Models\Academia\Sede;
use App\Services\Academia\Paquetes;
use App\Services\Academia\ClasesPrivadas;
use Illuminate\Support\Facades\Validator;
use App\Services\Academia\CalendarioCurso;

/**
 * Clases personalizadas (privadas o de pareja). Las toman alumnos (se descuentan de su paquete, ver
 * ClasesPrivadas) o una persona no registrada: la clase suelta, que solo guarda su nombre.
 * Si la clase se cobra aparte (tiene valor), su pago se registra aquí mismo y queda en la tabla pagos.
 *
 * Asistencia y cobro son de perfiles distintos: el resultado de la clase se registra con el permiso
 * Modificar de Clases personalizadas; registrar, cambiar o quitar su pago exige el permiso Pagar
 * de la misma opción (PagarClasePrivada).
 */
class ClasePrivadaController extends Controller
{
    private const RESULTADOS = ClasesPrivadas::RESULTADOS;
    private const ESTADOS = ['programada' => 'Programada', 'realizada' => 'Realizada', 'cancelada' => 'Cancelada'];

    private function alumnosDe(array $claseIds)
    {
        return DB::table('clase_privada_alumno as cpa')
            ->join('alumnos as a', 'a.id', '=', 'cpa.alumno_id')
            ->whereIn('cpa.clase_privada_id', $claseIds)
            ->orderBy('a.nombres')
            ->select('cpa.*', DB::raw("CONCAT(a.nombres,' ',a.apellidos) as nombre"))
            ->get()
            ->groupBy('clase_privada_id');
    }

    /** Reglas del cobro de la clase (valor y pago), comunes a agendar, modificar y registrar. */
    private const REGLAS_COBRO = [
        'valor' => 'nullable|numeric|min:0',
        'pagada' => 'nullable|boolean',
        'fecha_pago' => 'nullable|required_if:pagada,true,1|date',
        'metodo_pago' => 'nullable|in:efectivo,transferencia,tarjeta,otro',
        'referencia' => 'nullable|string|max:100',
    ];
    private const MENSAJES_COBRO = ['fecha_pago.required_if' => 'Indica la fecha del pago.'];

    private function errorDeCobro(Request $request, $valorActual = null): ?string
    {
        $valor = $request->has('valor') ? $request->valor : $valorActual;
        return $request->boolean('pagada') && (float) $valor <= 0 ? 'Indica el valor de la clase para registrar su pago.' : null;
    }

    /** Pago de cada clase (el que se registró desde la clase), por id de clase. */
    private function pagosDe(array $claseIds)
    {
        return DB::table('pagos')->whereIn('clase_privada_id', $claseIds)
            ->get(['id', 'clase_privada_id', 'monto', 'fecha_pago', 'metodo_pago', 'referencia'])
            ->keyBy('clase_privada_id');
    }

    private function cambiaElPago(object $pago, Request $request, $valor): bool
    {
        return (float) $pago->monto !== (float) $valor
            || $pago->fecha_pago !== Carbon::parse($request->fecha_pago)->toDateString()
            || $pago->metodo_pago !== ($request->metodo_pago ?: 'efectivo')
            || (string) $pago->referencia !== (string) $request->referencia;
    }

    /**
     * Mensaje si lo que la petición pide hacer con el pago de la clase (marcarla pagada, cambiar el
     * pago o quitarlo) requiere el permiso Pagar y el usuario no lo tiene; null si puede.
     */
    private function permisoDePagoFaltante(Request $request, ?int $claseId): ?string
    {
        if (!$request->has('pagada')) {
            return null;
        }
        $clase = $claseId ? DB::table('clases_privadas')->find($claseId) : null;
        $pago = $claseId ? DB::table('pagos')->where('clase_privada_id', $claseId)->first() : null;
        $pagada = $request->boolean('pagada');
        $valor = $request->has('valor') ? $request->valor : ($clase->valor ?? null);
        $accion = match (true) {
            $pagada && !$pago => 'registrar',
            !$pagada && (bool) $pago => 'quitar',
            $pagada && $this->cambiaElPago($pago, $request, $valor) => 'modificar',
            default => null,
        };
        return $accion && !Auth::user()->checkPermissionTo('PagarClasePrivada', 'api') ? "No tienes permiso para {$accion} el pago de la clase." : null;
    }

    /**
     * Deja el pago de la clase como lo indica la petición (solo si envía `pagada`): lo crea o
     * actualiza por el valor de la clase, o lo elimina si se desmarca.
     */
    private function sincronizarPago(int $claseId, Request $request): void
    {
        if (!$request->has('pagada')) {
            return;
        }
        $clase = DB::table('clases_privadas')->find($claseId);
        $pago = DB::table('pagos')->where('clase_privada_id', $claseId)->first();
        if (!$request->boolean('pagada')) {
            if ($pago) {
                Pago::eliminar($pago->id);
            }
            return;
        }
        if ($pago && !$this->cambiaElPago($pago, $request, $clase->valor)) {
            return;
        }
        // El pago queda a nombre del primer alumno de la clase o, en una clase suelta, de la persona.
        $alumnoId = DB::table('clase_privada_alumno')->where('clase_privada_id', $claseId)->orderBy('id')->value('alumno_id');
        Pago::modificarOCrear(array_merge($pago ? ['id' => $pago->id] : [], [
            'alumno_id' => $alumnoId,
            'pagador_nombre' => $alumnoId ? null : $clase->externo_nombre,
            'clase_privada_id' => $claseId,
            'sede_id' => $clase->sede_id,
            'monto' => $clase->valor,
            'fecha_pago' => Carbon::parse($request->fecha_pago)->toDateString(),
            'metodo_pago' => $request->metodo_pago ?: 'efectivo',
            'referencia' => $request->referencia,
            'observacion' => 'Clase personalizada del ' . Carbon::parse($clase->fecha)->format('d/m/Y'),
        ]), false);
    }

    private function formatear(object $c, $alumnos, bool $detalle = false, ?object $pago = null): array
    {
        $lista = collect($alumnos)->map(function ($a) use ($detalle, $c) {
            $fila = [
                'alumno_id' => $a->alumno_id,
                'nombre' => $a->nombre,
                'resultado' => $a->resultado,
                'resultado_nombre' => self::RESULTADOS[$a->resultado],
                'cancelado_en' => $a->cancelado_en,
                'descuenta' => (bool) $a->descuenta,
            ];
            if ($detalle) {
                $fila['paquete'] = Paquetes::disponiblesHoy($a->alumno_id);
                $fila['descontada'] = DB::table('consumos_paquete')
                    ->where('clase_privada_id', $c->id)->where('alumno_id', $a->alumno_id)->exists();
            }
            return $fila;
        })->values();

        return [
            'id' => $c->id,
            'fecha' => $c->fecha,
            'hora' => substr((string) $c->hora, 0, 5),
            'duracion_min' => $c->duracion_min,
            'sede_id' => $c->sede_id,
            'sede_nombre' => $c->sede_nombre ?? null,
            'profesor_id' => $c->profesor_id,
            'profesor_nombre' => $c->profesor_nombre ?? null,
            'estado' => $c->estado,
            'estado_nombre' => self::ESTADOS[$c->estado],
            'observacion' => $c->observacion,
            'externo_nombre' => $c->externo_nombre,
            'externo_telefono' => $c->externo_telefono,
            'externo_resultado' => $c->externo_resultado,
            'externo_resultado_nombre' => ClasesPrivadas::RESULTADOS_EXTERNO[$c->externo_resultado] ?? null,
            'valor' => $c->valor !== null ? (float) $c->valor : null,
            'pago' => $pago,
            'pagada' => (bool) $pago,
            // Para la lista: las clases sin valor se pagan con el paquete.
            'cobro' => (float) $c->valor > 0 ? ($pago ? 'pagada' : 'por_cobrar') : 'paquete',
            'cobro_nombre' => (float) $c->valor > 0 ? ($pago ? 'Pagada' : 'Por cobrar') : 'Con paquete',
            'alumnos' => $detalle ? $lista : $lista->pluck('alumno_id'),
            'detalle_alumnos' => $lista,
            'alumnos_nombres' => $lista->pluck('nombre')
                ->concat($c->externo_nombre ? ["{$c->externo_nombre} (no registrado)"] : [])->implode(', '),
            'usuario_creacion_nombre' => $c->usuario_creacion_nombre,
            'usuario_modificacion_nombre' => $c->usuario_modificacion_nombre,
            'fecha_creacion' => Carbon::parse($c->created_at)->format('Y-m-d H:i:s'),
            'fecha_modificacion' => Carbon::parse($c->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function consulta()
    {
        return DB::table('clases_privadas as c')
            ->leftJoin('profesores as p', 'p.id', '=', 'c.profesor_id')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->select('c.*', DB::raw("CONCAT(p.nombres,' ',p.apellidos) as profesor_nombre"), 's.nombre as sede_nombre');
    }

    public function index(Request $request)
    {
        $query = Sede::filtrar($this->consulta(), 'c.sede_id');
        foreach (['profesor_id' => 'c.profesor_id', 'estado' => 'c.estado'] as $filtro => $columna) {
            if ($request->filled($filtro)) {
                $query->where($columna, $request->$filtro);
            }
        }
        if ($request->filled('alumno_id')) {
            $query->whereExists(fn ($q) => $q->from('clase_privada_alumno')
                ->whereColumn('clase_privada_id', 'c.id')->where('alumno_id', $request->alumno_id));
        }
        if ($request->filled('fecha_desde')) {
            $query->where('c.fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('c.fecha', '<=', $request->fecha_hasta);
        }
        $pagina = $query->orderBy('c.fecha', 'desc')->orderBy('c.hora', 'desc')->paginate((int) ($request->limite ?? 100));
        $ids = $pagina->getCollection()->pluck('id')->all();
        $alumnos = $this->alumnosDe($ids);
        $pagos = $this->pagosDe($ids);
        return response([
            'datos' => $pagina->getCollection()->map(fn ($c) => $this->formatear($c, $alumnos[$c->id] ?? [], false, $pagos[$c->id] ?? null))->all(),
            'desde' => $pagina->firstItem(),
            'hasta' => $pagina->lastItem(),
            'por_pagina' => $pagina->perPage(),
            'pagina_actual' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $clase = $this->consulta()->where('c.id', $id)->first();
        if (!$clase) {
            return response(get_response_body(['La clase no existe.']), Response::HTTP_NOT_FOUND);
        }
        $datos = $this->formatear($clase, $this->alumnosDe([$id])[$id] ?? [], true, $this->pagosDe([$id])[$id] ?? null);
        $datos['alumnos'] = collect($datos['detalle_alumnos'])->pluck('alumno_id');
        $datos['horas_cancelacion'] = ClasesPrivadas::horasCancelacion();
        return response($datos, Response::HTTP_OK);
    }

    private function validar(Request $request)
    {
        $request->merge(['sede_id' => Sede::resolver($request->sede_id)]);
        $validator = Validator::make($request->all(), self::REGLAS_COBRO + [
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'duracion_min' => 'required|integer|between:15,300',
            'sede_id' => 'bail|required|integer|exists:sedes,id',
            'profesor_id' => 'nullable|integer|exists:profesores,id',
            'alumnos' => 'required_without:externo_nombre|array|max:10',
            'alumnos.*' => 'integer|exists:alumnos,id',
            'externo_nombre' => 'nullable|string|max:150',
            'externo_telefono' => 'nullable|string|max:30',
            'observacion' => 'nullable|string',
        ], self::MENSAJES_COBRO + [
            'alumnos.required_without' => 'Elige al menos un alumno o escribe el nombre de la persona que toma la clase.',
            'sede_id.required' => 'Elige la sede de la clase.',
        ]);
        if ($validator->fails()) {
            return format_messages_validator($validator);
        }
        if ($error = $this->errorDeCobro($request)) {
            return [$error];
        }
        $noLectivo = (new CalendarioCurso())->noLectivo(Carbon::parse($request->fecha), (int) $request->sede_id);
        if ($noLectivo) {
            $tipo = $noLectivo['tipo'] === 'festivo' ? 'es festivo' : 'la academia está cerrada';
            return [Carbon::parse($request->fecha)->format('d/m/Y') . " {$tipo} ({$noLectivo['motivo']})."];
        }
        return null;
    }

    private function guardar(Request $request, ?int $id = null): int
    {
        $usuario = Auth::user()->usuario();
        $datos = [
            'fecha' => Carbon::parse($request->fecha)->toDateString(),
            'hora' => $request->hora,
            'duracion_min' => $request->duracion_min,
            'sede_id' => $request->sede_id,
            'profesor_id' => $request->profesor_id,
            'observacion' => $request->observacion,
            'externo_nombre' => $request->externo_nombre ?: null,
            'externo_telefono' => $request->externo_nombre ? $request->externo_telefono : null,
            'valor' => $request->filled('valor') ? $request->valor : null,
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'updated_at' => Carbon::now(),
        ];
        if ($id) {
            DB::table('clases_privadas')->where('id', $id)->update($datos);
        } else {
            $id = DB::table('clases_privadas')->insertGetId(array_merge($datos, [
                'estado' => 'programada',
                'usuario_creacion_id' => $usuario->id,
                'usuario_creacion_nombre' => $usuario->nombre,
                'created_at' => Carbon::now(),
            ]));
        }

        $actuales = DB::table('clase_privada_alumno')->where('clase_privada_id', $id)->pluck('alumno_id')->all();
        $nuevos = array_map('intval', $request->alumnos ?? []);
        DB::table('clase_privada_alumno')->where('clase_privada_id', $id)->whereNotIn('alumno_id', $nuevos)->delete();
        foreach (array_diff($nuevos, $actuales) as $alumnoId) {
            DB::table('clase_privada_alumno')->insert([
                'clase_privada_id' => $id, 'alumno_id' => $alumnoId, 'resultado' => 'pendiente',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
        }
        $this->sincronizarPago($id, $request);
        return $id;
    }

    /** Aviso (no bloquea) de alumnos sin clases disponibles en paquetes vigentes. */
    private function avisoSinPaquete(array $alumnos): string
    {
        $sin = collect($alumnos)->filter(fn ($id) => Paquetes::disponiblesHoy((int) $id)['restantes'] <= 0);
        if ($sin->isEmpty()) {
            return '';
        }
        $nombres = DB::table('alumnos')->whereIn('id', $sin)->get()->map(fn ($a) => "{$a->nombres} {$a->apellidos}")->implode(', ');
        return " Atención: {$nombres} no tiene(n) clases disponibles en un paquete vigente.";
    }

    public function store(Request $request)
    {
        if ($errores = $this->validar($request)) {
            return response(get_response_body($errores), Response::HTTP_BAD_REQUEST);
        }
        if ($error = $this->permisoDePagoFaltante($request, null)) {
            return response(get_response_body([$error]), Response::HTTP_FORBIDDEN);
        }
        $id = DB::transaction(fn () => $this->guardar($request));
        return response(get_response_body(['La clase ha sido agendada.' . $this->avisoSinPaquete($request->alumnos ?? []), 2],
            ['id' => $id]), Response::HTTP_CREATED);
    }

    public function update(Request $request, $id)
    {
        $clase = DB::table('clases_privadas')->find($id);
        if (!$clase) {
            return response(get_response_body(['La clase no existe.']), Response::HTTP_NOT_FOUND);
        }
        if ($clase->estado !== 'programada') {
            return response(get_response_body(['Solo se pueden modificar clases programadas.']), Response::HTTP_CONFLICT);
        }
        if ($errores = $this->validar($request)) {
            return response(get_response_body($errores), Response::HTTP_BAD_REQUEST);
        }
        if ($error = $this->permisoDePagoFaltante($request, (int) $id)) {
            return response(get_response_body([$error]), Response::HTTP_FORBIDDEN);
        }
        DB::transaction(fn () => $this->guardar($request, (int) $id));
        return response(get_response_body(['La clase ha sido modificada.' . $this->avisoSinPaquete($request->alumnos ?? []), 1],
            ['id' => (int) $id]), Response::HTTP_OK);
    }

    /**
     * Resultado por alumno: [{ alumno_id, resultado: asistio|no_asistio|cancelo, a_tiempo?, cancelado_en? }]
     * y, en una clase suelta, externo_resultado. Aplica el descuento y actualiza el estado de la clase.
     * Solo asistencia: el cobro va por pago().
     */
    public function registrar(Request $request, $id)
    {
        $clase = DB::table('clases_privadas')->find($id);
        if (!$clase) {
            return response(get_response_body(['La clase no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'resultados' => 'nullable|array',
            'resultados.*.alumno_id' => 'required|integer',
            'resultados.*.resultado' => 'required|in:asistio,no_asistio,cancelo',
            'resultados.*.a_tiempo' => 'nullable|boolean',
            'resultados.*.cancelado_en' => 'nullable|date',
            'externo_resultado' => 'nullable|in:' . implode(',', array_keys(ClasesPrivadas::RESULTADOS_EXTERNO)),
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $sinPaquete = [];
        try {
            DB::transaction(function () use ($request, $clase, &$sinPaquete) {
                foreach ($request->resultados ?? [] as $r) {
                    $fila = DB::table('clase_privada_alumno')
                        ->where('clase_privada_id', $clase->id)->where('alumno_id', $r['alumno_id'])->first();
                    if (!$fila) {
                        continue;
                    }
                    $aTiempo = isset($r['a_tiempo']) ? (bool) $r['a_tiempo'] : null;
                    if (!ClasesPrivadas::aplicarResultado($clase, $fila, $r['resultado'], $aTiempo, $r['cancelado_en'] ?? null)) {
                        $sinPaquete[] = (int) $r['alumno_id'];
                    }
                }
                if ($clase->externo_nombre && $request->filled('externo_resultado')) {
                    DB::table('clases_privadas')->where('id', $clase->id)->update(['externo_resultado' => $request->externo_resultado]);
                }
                ClasesPrivadas::actualizarEstado((int) $clase->id);
            });
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $mensaje = 'Resultado registrado.';
        if ($sinPaquete) {
            $nombres = DB::table('alumnos')->whereIn('id', $sinPaquete)->get()->map(fn ($a) => "{$a->nombres} {$a->apellidos}")->implode(', ');
            $mensaje .= " {$nombres} no tenía(n) paquete vigente: la clase quedó sin descontar (cóbrala aparte o véndele un paquete).";
        }
        return response(get_response_body([$mensaje, 1], ['id' => (int) $id]), Response::HTTP_OK);
    }

    /**
     * Cobro de la clase: su valor y si está pagada { valor, pagada, fecha_pago, metodo_pago, referencia }.
     * Tiene permiso propio: la ruta exige PagarClasePrivada, no el de modificar la clase.
     */
    public function pago(Request $request, $id)
    {
        $clase = DB::table('clases_privadas')->find($id);
        if (!$clase) {
            return response(get_response_body(['La clase no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), self::REGLAS_COBRO + ['pagada' => 'required|boolean'], self::MENSAJES_COBRO);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ($error = $this->errorDeCobro($request, $clase->valor)) {
            return response(get_response_body([$error]), Response::HTTP_BAD_REQUEST);
        }
        if ($error = $this->permisoDePagoFaltante($request, (int) $id)) {
            return response(get_response_body([$error]), Response::HTTP_FORBIDDEN);
        }
        try {
            DB::transaction(function () use ($request, $clase) {
                if ($request->has('valor')) {
                    DB::table('clases_privadas')->where('id', $clase->id)->update(['valor' => $request->filled('valor') ? $request->valor : null]);
                }
                $this->sincronizarPago((int) $clase->id, $request);
            });
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response(get_response_body(['El cobro de la clase ha sido guardado.', 1], ['id' => (int) $id]), Response::HTTP_OK);
    }

    public function destroy($id)
    {
        if (DB::table('consumos_paquete')->where('clase_privada_id', $id)->exists()) {
            return response(get_response_body(['No se puede eliminar: ya descontó clases de paquetes.']), Response::HTTP_CONFLICT);
        }
        if (DB::table('pagos')->where('clase_privada_id', $id)->exists()) {
            return response(get_response_body(['No se puede eliminar: la clase tiene un pago registrado. Desmarca "Pagada" primero.']), Response::HTTP_CONFLICT);
        }
        DB::table('clases_privadas')->where('id', $id)->delete();
        return response(get_response_body(['La clase ha sido eliminada.', 3]), Response::HTTP_OK);
    }
}
