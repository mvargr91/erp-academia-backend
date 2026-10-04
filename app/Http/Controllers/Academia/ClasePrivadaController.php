<?php

namespace App\Http\Controllers\Academia;

use App\Support\Configuracion\Configuracion;
use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Academia\Sede;
use App\Services\Academia\Paquetes;
use Illuminate\Support\Facades\Validator;
use App\Services\Academia\CalendarioCurso;

/**
 * Clases personalizadas (privadas o de pareja). Se descuentan del paquete de cada alumno:
 *  - asistió / no asistió: descuenta 1 clase;
 *  - canceló con al menos HORAS_CANCELACION de anticipación: no descuenta; si fue tarde: descuenta.
 */
class ClasePrivadaController extends Controller
{
    /** Horas mínimas para cancelar sin descuento (parámetro HORAS_CANCELACION_CLASE). */
    public static function horasCancelacion(): int
    {
        return Configuracion::entero('HORAS_CANCELACION_CLASE', 24);
    }

    private const RESULTADOS = [
        'pendiente' => 'Pendiente',
        'asistio' => 'Asistió',
        'no_asistio' => 'No asistió',
        'cancelo' => 'Canceló',
    ];
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

    private function formatear(object $c, $alumnos, bool $detalle = false): array
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
            'alumnos' => $detalle ? $lista : $lista->pluck('alumno_id'),
            'detalle_alumnos' => $lista,
            'alumnos_nombres' => $lista->pluck('nombre')->implode(', '),
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
        $alumnos = $this->alumnosDe($pagina->getCollection()->pluck('id')->all());
        return response([
            'datos' => $pagina->getCollection()->map(fn ($c) => $this->formatear($c, $alumnos[$c->id] ?? []))->all(),
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
        $datos = $this->formatear($clase, $this->alumnosDe([$id])[$id] ?? [], true);
        $datos['alumnos'] = collect($datos['detalle_alumnos'])->pluck('alumno_id');
        $datos['horas_cancelacion'] = self::horasCancelacion();
        return response($datos, Response::HTTP_OK);
    }

    private function validar(Request $request)
    {
        $request->merge(['sede_id' => Sede::resolver($request->sede_id)]);
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'duracion_min' => 'required|integer|between:15,300',
            'sede_id' => 'bail|required|integer|exists:sedes,id',
            'profesor_id' => 'nullable|integer|exists:profesores,id',
            'alumnos' => 'required|array|min:1|max:10',
            'alumnos.*' => 'integer|exists:alumnos,id',
            'observacion' => 'nullable|string',
        ], ['alumnos.required' => 'Elige al menos un alumno.', 'sede_id.required' => 'Elige la sede de la clase.']);
        if ($validator->fails()) {
            return format_messages_validator($validator);
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
        $nuevos = array_map('intval', $request->alumnos);
        DB::table('clase_privada_alumno')->where('clase_privada_id', $id)->whereNotIn('alumno_id', $nuevos)->delete();
        foreach (array_diff($nuevos, $actuales) as $alumnoId) {
            DB::table('clase_privada_alumno')->insert([
                'clase_privada_id' => $id, 'alumno_id' => $alumnoId, 'resultado' => 'pendiente',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
        }
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
        $id = DB::transaction(fn () => $this->guardar($request));
        return response(get_response_body(['La clase ha sido agendada.' . $this->avisoSinPaquete($request->alumnos), 2],
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
        DB::transaction(fn () => $this->guardar($request, (int) $id));
        return response(get_response_body(['La clase ha sido modificada.' . $this->avisoSinPaquete($request->alumnos), 1],
            ['id' => (int) $id]), Response::HTTP_OK);
    }

    /**
     * Resultado por alumno: [{ alumno_id, resultado: asistio|no_asistio|cancelo, cancelado_en? }].
     * Aplica la regla de descuento y deja la clase realizada (o cancelada si nadie la tomó).
     */
    public function registrar(Request $request, $id)
    {
        $clase = DB::table('clases_privadas')->find($id);
        if (!$clase) {
            return response(get_response_body(['La clase no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'resultados' => 'required|array|min:1',
            'resultados.*.alumno_id' => 'required|integer',
            'resultados.*.resultado' => 'required|in:asistio,no_asistio,cancelo',
            'resultados.*.cancelado_en' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $inicio = Carbon::parse($clase->fecha . ' ' . $clase->hora);
        $horasCancelacion = self::horasCancelacion();
        $fecha = Carbon::parse($clase->fecha);
        $sinPaquete = [];

        try {
            DB::transaction(function () use ($request, $clase, $inicio, $fecha, $horasCancelacion, &$sinPaquete) {
                foreach ($request->resultados as $r) {
                    $fila = DB::table('clase_privada_alumno')
                        ->where('clase_privada_id', $clase->id)->where('alumno_id', $r['alumno_id'])->first();
                    if (!$fila) {
                        continue;
                    }
                    $canceladoEn = $r['resultado'] === 'cancelo' ? Carbon::parse($r['cancelado_en'] ?? Carbon::now()) : null;
                    $descuenta = match ($r['resultado']) {
                        'asistio', 'no_asistio' => true,
                        'cancelo' => $canceladoEn->diffInHours($inicio, false) < $horasCancelacion,
                    };
                    DB::table('clase_privada_alumno')->where('id', $fila->id)->update([
                        'resultado' => $r['resultado'],
                        'cancelado_en' => $canceladoEn,
                        'descuenta' => $descuenta,
                        'updated_at' => Carbon::now(),
                    ]);

                    if ($descuenta) {
                        $motivo = $r['resultado'] === 'cancelo' ? 'cancelacion_tardia' : $r['resultado'];
                        if (!Paquetes::consumir((int) $r['alumno_id'], $fecha, 'privada', null, $clase->id, $motivo)) {
                            $sinPaquete[] = (int) $r['alumno_id'];
                        }
                    } else {
                        Paquetes::devolver((int) $r['alumno_id'], null, $clase->id);
                    }
                }

                $resultados = DB::table('clase_privada_alumno')->where('clase_privada_id', $clase->id)->pluck('resultado');
                $estado = $resultados->every(fn ($x) => $x === 'cancelo') ? 'cancelada'
                    : ($resultados->contains('pendiente') ? 'programada' : 'realizada');
                DB::table('clases_privadas')->where('id', $clase->id)->update(['estado' => $estado, 'updated_at' => Carbon::now()]);
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

    public function destroy($id)
    {
        if (DB::table('consumos_paquete')->where('clase_privada_id', $id)->exists()) {
            return response(get_response_body(['No se puede eliminar: ya descontó clases de paquetes.']), Response::HTTP_CONFLICT);
        }
        DB::table('clases_privadas')->where('id', $id)->delete();
        return response(get_response_body(['La clase ha sido eliminada.', 3]), Response::HTTP_OK);
    }
}
