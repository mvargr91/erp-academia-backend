<?php

namespace App\Http\Controllers\Academia;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Alumno;
use App\Models\Academia\Sede;
use App\Services\Academia\Vacacionales;
use Illuminate\Support\Facades\Validator;

/**
 * Alumnos de cursos vacacionales: son los mismos alumnos de la academia (una sola ficha por persona).
 * El acudiente se guarda como contacto de emergencia del alumno. La lista muestra a quienes tienen
 * inscripciones a vacacionales; al buscar por nombre aparecen todos los de la academia.
 */
class VacacionalAlumnoController extends Controller
{
    /** Campo del formulario de vacacionales => columna de alumnos. */
    private const CAMPOS = [
        'nombres' => 'nombres', 'apellidos' => 'apellidos', 'documento' => 'documento',
        'fecha_nacimiento' => 'fecha_nacimiento', 'telefono' => 'telefono', 'correo' => 'correo',
        'acudiente_nombre' => 'contacto_emergencia', 'acudiente_telefono' => 'telefono_emergencia', 'estado' => 'estado',
    ];

    private function formatear(object $a): array
    {
        return array_merge([
            'id' => $a->id,
            'nombre' => trim("{$a->nombres} {$a->apellidos}"),
            'nombres' => $a->nombres,
            'apellidos' => $a->apellidos,
            'documento' => $a->documento,
            'fecha_nacimiento' => $a->fecha_nacimiento,
            'telefono' => $a->telefono,
            'correo' => $a->correo,
            'acudiente_nombre' => $a->contacto_emergencia,
            'acudiente_telefono' => $a->telefono_emergencia,
            'estado' => $a->estado,
            'inscripciones' => (int) ($a->inscripciones ?? 0),
            'ultimo_vacacional' => $a->ultimo_vacacional ?? null,
            'cursos_regulares' => (int) ($a->cursos_regulares ?? 0),
        ], Vacacionales::auditoriaDe($a));
    }

    private function consulta()
    {
        return DB::table('alumnos as a')->select('a.*')
            ->selectSub(DB::table('vacacional_inscripciones as i')->whereColumn('i.alumno_id', 'a.id')->where('i.estado', 1)
                ->selectRaw('COUNT(*)'), 'inscripciones')
            ->selectSub(DB::table('vacacional_inscripciones as i')->join('vacacional_cursos as c', 'c.id', '=', 'i.curso_id')
                ->whereColumn('i.alumno_id', 'a.id')->orderBy('i.id', 'desc')->limit(1)->select('c.nombre'), 'ultimo_vacacional')
            ->selectSub(DB::table('curso_alumno as ca')->whereColumn('ca.alumno_id', 'a.id')->where('ca.estado', 1)
                ->selectRaw('COUNT(*)'), 'cursos_regulares');
    }

    public function index(Request $request)
    {
        // Para elegir al inscribir: todos los alumnos activos de la academia.
        if ($request->ligera) {
            return response(DB::table('alumnos')->where('estado', 1)->orderBy('nombres')
                ->get(['id', DB::raw("CONCAT(nombres,' ',apellidos) as nombre"), 'correo']), Response::HTTP_OK);
        }

        $query = $this->consulta();
        if ($request->filled('nombre')) {
            foreach (preg_split('/\s+/', trim($request->nombre), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
                $query->where(DB::raw("CONCAT(a.nombres,' ',a.apellidos)"), 'like', "%{$palabra}%");
            }
        } else {
            // Sin búsqueda: los que tienen inscripciones a vacacionales.
            $query->whereExists(fn ($s) => $s->from('vacacional_inscripciones as i')->whereColumn('i.alumno_id', 'a.id'));
        }
        return response(
            Vacacionales::paginar($query->orderBy('a.nombres')->orderBy('a.apellidos'), $request, fn ($a) => $this->formatear($a)),
            Response::HTTP_OK
        );
    }

    public function show($id)
    {
        $alumno = $this->consulta()->where('a.id', $id)->first();
        if (!$alumno) {
            return response(get_response_body(['El alumno no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->formatear($alumno), Response::HTTP_OK);
    }

    private function guardar(Request $request, ?int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'documento' => 'nullable|string|max:30',
            'fecha_nacimiento' => 'nullable|date',
            'telefono' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:150',
            'acudiente_nombre' => 'nullable|string|max:150',
            'acudiente_telefono' => 'nullable|string|max:30',
            'estado' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ($id && !DB::table('alumnos')->where('id', $id)->exists()) {
            return response(get_response_body(['El alumno no existe.']), Response::HTTP_NOT_FOUND);
        }

        $datos = Vacacionales::auditoria(!$id);
        foreach (self::CAMPOS as $campo => $columna) {
            if ($request->exists($campo)) {
                $datos[$columna] = $request->input($campo);
            }
        }
        if ($id) {
            DB::table('alumnos')->where('id', $id)->update($datos);
        } else {
            $id = DB::table('alumnos')->insertGetId($datos + ['sede_id' => Sede::resolver($request->sede_id)]);
        }
        return $this->formatear($this->consulta()->where('a.id', $id)->first());
    }

    public function store(Request $request)
    {
        $alumno = $this->guardar($request, null);
        return is_array($alumno)
            ? response(get_response_body(['El alumno ha sido creado.', 2], $alumno), Response::HTTP_CREATED)
            : $alumno;
    }

    public function update(Request $request, $id)
    {
        $alumno = $this->guardar($request, (int) $id);
        return is_array($alumno)
            ? response(get_response_body(['El alumno ha sido modificado.', 1], $alumno), Response::HTTP_OK)
            : $alumno;
    }

    public function destroy($id)
    {
        if (DB::table('vacacional_inscripciones')->where('alumno_id', $id)->exists()) {
            return response(
                get_response_body(['El alumno tiene inscripciones a vacacionales: márcalo como inactivo en lugar de eliminarlo.']),
                Response::HTTP_CONFLICT
            );
        }
        if (DB::table('curso_alumno')->where('alumno_id', $id)->exists()) {
            return response(get_response_body(['El alumno tiene cursos en la academia: elimínalo desde Alumnos.']), Response::HTTP_CONFLICT);
        }
        Alumno::eliminar($id);
        return response(get_response_body(['El alumno ha sido eliminado.', 3]), Response::HTTP_OK);
    }
}
