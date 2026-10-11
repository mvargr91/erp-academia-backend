<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Academia\Sede;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Academia\Vacacionales;
use Illuminate\Support\Facades\Validator;

/**
 * Cursos vacacionales: género (ritmo), profesor y las fechas de clase elegidas una a una en el
 * calendario (pueden ser días discontinuos). Cada alumno toma `clases_por_alumno` de esas fechas.
 */
class VacacionalCursoController extends Controller
{
    private const CAMPOS = [
        'nombre', 'ritmo_id', 'profesor_id', 'hora_inicio', 'hora_fin',
        'clases_por_alumno', 'valor', 'cupo_max', 'descripcion', 'estado',
    ];

    private function consulta()
    {
        return DB::table('vacacional_cursos as c')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->leftJoin('profesores as p', 'p.id', '=', 'c.profesor_id')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->select('c.*', 'r.nombre as ritmo_nombre', 's.nombre as sede_nombre', DB::raw("CONCAT(p.nombres,' ',p.apellidos) as profesor_nombre"))
            ->selectSub(DB::table('vacacional_inscripciones as i')->whereColumn('i.curso_id', 'c.id')->where('i.estado', 1)
                ->selectRaw('COUNT(*)'), 'inscritos')
            ->selectSub(DB::table('vacacional_curso_fechas as f')->whereColumn('f.curso_id', 'c.id')->selectRaw('MIN(f.fecha)'), 'fecha_inicio')
            ->selectSub(DB::table('vacacional_curso_fechas as f')->whereColumn('f.curso_id', 'c.id')->selectRaw('MAX(f.fecha)'), 'fecha_fin');
    }

    private function formatear(object $c): array
    {
        $fechas = Vacacionales::fechasCurso($c->id);
        $hoy = Carbon::today()->toDateString();
        $momento = !$fechas || $c->fecha_fin < $hoy ? 'finalizado' : ($c->fecha_inicio > $hoy ? 'proximo' : 'en_curso');
        return array_merge([
            'id' => $c->id,
            'nombre' => $c->nombre,
            'sede_id' => $c->sede_id,
            'sede_nombre' => $c->sede_nombre,
            'ritmo_id' => $c->ritmo_id,
            'ritmo_nombre' => $c->ritmo_nombre,
            'profesor_id' => $c->profesor_id,
            'profesor_nombre' => $c->profesor_nombre,
            'hora_inicio' => $c->hora_inicio ? substr((string) $c->hora_inicio, 0, 5) : null,
            'hora_fin' => $c->hora_fin ? substr((string) $c->hora_fin, 0, 5) : null,
            'clases_por_alumno' => (int) $c->clases_por_alumno,
            'valor' => (float) $c->valor,
            'cupo_max' => $c->cupo_max,
            'descripcion' => $c->descripcion,
            'estado' => $c->estado,
            'inscritos' => (int) $c->inscritos,
            'fechas' => $fechas,
            'fechas_texto' => Vacacionales::fechasTexto($fechas),
            'total_fechas' => count($fechas),
            'fecha_inicio' => $c->fecha_inicio,
            'fecha_fin' => $c->fecha_fin,
            'momento' => $momento,
            'momento_nombre' => ['proximo' => 'Próximo', 'en_curso' => 'En curso', 'finalizado' => 'Finalizado'][$momento],
        ], Vacacionales::auditoriaDe($c));
    }

    public function index(Request $request)
    {
        $query = $this->consulta();
        if ($request->filled('nombre')) {
            $query->where('c.nombre', 'like', '%' . $request->nombre . '%');
        }
        if ($request->filled('ritmo_id')) {
            $query->where('c.ritmo_id', $request->ritmo_id);
        }

        // Selectores (inscripciones, correos): cursos activos de cualquier sede, los más recientes primero.
        if ($request->ligera) {
            return response(
                $query->where('c.estado', 1)->orderBy('fecha_inicio', 'desc')->get()->map(fn ($c) => $this->formatear($c))->values(),
                Response::HTTP_OK
            );
        }

        $hoy = Carbon::today()->toDateString();
        $ultima = DB::table('vacacional_curso_fechas as f')->whereColumn('f.curso_id', 'c.id')->selectRaw('MAX(f.fecha)');
        if ($request->momento === 'finalizado') {
            $query->where($ultima, '<', $hoy);
        } elseif ($request->momento === 'vigente') {
            $query->where($ultima, '>=', $hoy);
        }
        Sede::filtrar($query, 'c.sede_id')->orderBy('fecha_inicio', 'desc')->orderBy('c.id', 'desc');
        return response(Vacacionales::paginar($query, $request, fn ($c) => $this->formatear($c)), Response::HTTP_OK);
    }

    public function show($id)
    {
        $curso = $this->consulta()->where('c.id', $id)->first();
        if (!$curso) {
            return response(get_response_body(['El curso vacacional no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->formatear($curso), Response::HTTP_OK);
    }

    private function guardar(Request $request, ?int $id)
    {
        $datos = $request->all();
        $datos['sede_id'] = Sede::resolver($request->sede_id);
        $validator = Validator::make($datos, [
            'nombre' => 'required|string|max:150',
            'sede_id' => 'required|integer|exists:sedes,id',
            'ritmo_id' => 'nullable|integer|exists:ritmos,id',
            'profesor_id' => 'nullable|integer|exists:profesores,id',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'clases_por_alumno' => 'required|integer|min:1|max:60',
            'valor' => 'required|numeric|min:0',
            'cupo_max' => 'nullable|integer|min:1',
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'required|boolean',
            'fechas' => 'required|array|min:1',
            'fechas.*' => 'date_format:Y-m-d|distinct',
        ], [
            'sede_id.required' => 'Elige la sede del curso.',
            'fechas.required' => 'Elige en el calendario los días de clase.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
        ]);
        $validator->after(function ($v) use ($request, $id) {
            $fechas = (array) $request->fechas;
            if (count($fechas) && (int) $request->clases_por_alumno > count($fechas)) {
                $v->errors()->add('clases_por_alumno', 'Cada alumno no puede tomar más clases que los días elegidos en el calendario.');
            }
            if ($id) {
                // No se puede quitar un día que algún alumno ya tiene entre sus clases.
                $ocupadas = DB::table('vacacional_inscripcion_fechas as f')
                    ->join('vacacional_inscripciones as i', 'i.id', '=', 'f.inscripcion_id')
                    ->where('i.curso_id', $id)->whereNotIn('f.fecha', $fechas ?: ['0000-00-00'])
                    ->distinct()->orderBy('f.fecha')->pluck('f.fecha')->all();
                if ($ocupadas) {
                    $v->errors()->add('fechas', 'Hay alumnos inscritos con clase el ' . Vacacionales::fechasTexto($ocupadas) . ': no se puede quitar del calendario.');
                }
            }
        });
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ($id && !DB::table('vacacional_cursos')->where('id', $id)->exists()) {
            return response(get_response_body(['El curso vacacional no existe.']), Response::HTTP_NOT_FOUND);
        }

        $id = DB::transaction(function () use ($request, $datos, $id) {
            $fila = array_merge(
                array_fill_keys(self::CAMPOS, null),
                $request->only(self::CAMPOS),
                ['sede_id' => $datos['sede_id']],
                Vacacionales::auditoria(!$id)
            );
            if ($id) {
                DB::table('vacacional_cursos')->where('id', $id)->update($fila);
            } else {
                $id = DB::table('vacacional_cursos')->insertGetId($fila);
            }
            DB::table('vacacional_curso_fechas')->where('curso_id', $id)->whereNotIn('fecha', $request->fechas)->delete();
            foreach ($request->fechas as $fecha) {
                DB::table('vacacional_curso_fechas')->insertOrIgnore(['curso_id' => $id, 'fecha' => $fecha]);
            }
            return $id;
        });
        return $this->formatear($this->consulta()->where('c.id', $id)->first());
    }

    public function store(Request $request)
    {
        $curso = $this->guardar($request, null);
        return is_array($curso)
            ? response(get_response_body(['El curso vacacional ha sido creado.', 2], $curso), Response::HTTP_CREATED)
            : $curso;
    }

    public function update(Request $request, $id)
    {
        $curso = $this->guardar($request, (int) $id);
        return is_array($curso)
            ? response(get_response_body(['El curso vacacional ha sido modificado.', 1], $curso), Response::HTTP_OK)
            : $curso;
    }

    public function destroy($id)
    {
        if (DB::table('vacacional_inscripciones')->where('curso_id', $id)->exists()) {
            return response(
                get_response_body(['El curso tiene alumnos inscritos: márcalo como inactivo en lugar de eliminarlo.']),
                Response::HTTP_CONFLICT
            );
        }
        DB::table('vacacional_cursos')->where('id', $id)->delete();
        return response(get_response_body(['El curso vacacional ha sido eliminado.', 3]), Response::HTTP_OK);
    }
}
