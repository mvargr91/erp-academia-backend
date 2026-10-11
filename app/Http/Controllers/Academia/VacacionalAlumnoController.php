<?php

namespace App\Http\Controllers\Academia;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Academia\Vacacionales;
use Illuminate\Support\Facades\Validator;

/** Alumnos de cursos vacacionales: un público aparte de los alumnos de la academia. */
class VacacionalAlumnoController extends Controller
{
    private const CAMPOS = [
        'nombres', 'apellidos', 'documento', 'fecha_nacimiento', 'telefono', 'correo',
        'acudiente_nombre', 'acudiente_telefono', 'observacion', 'estado',
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
            'acudiente_nombre' => $a->acudiente_nombre,
            'acudiente_telefono' => $a->acudiente_telefono,
            'observacion' => $a->observacion,
            'estado' => $a->estado,
            'inscripciones' => (int) ($a->inscripciones ?? 0),
            'ultimo_vacacional' => $a->ultimo_vacacional ?? null,
        ], Vacacionales::auditoriaDe($a));
    }

    private function consulta()
    {
        return DB::table('vacacional_alumnos as a')->select('a.*')
            ->selectSub(DB::table('vacacional_inscripciones as i')->whereColumn('i.alumno_id', 'a.id')->where('i.estado', 1)
                ->selectRaw('COUNT(*)'), 'inscripciones')
            ->selectSub(DB::table('vacacional_inscripciones as i')->join('vacacional_cursos as c', 'c.id', '=', 'i.curso_id')
                ->whereColumn('i.alumno_id', 'a.id')->orderBy('i.id', 'desc')->limit(1)->select('c.nombre'), 'ultimo_vacacional');
    }

    public function index(Request $request)
    {
        if ($request->ligera) {
            return response(DB::table('vacacional_alumnos')->where('estado', 1)->orderBy('nombres')
                ->get(['id', DB::raw("CONCAT(nombres,' ',apellidos) as nombre"), 'correo']), Response::HTTP_OK);
        }

        $query = $this->consulta();
        if ($request->filled('nombre')) {
            $query->where(DB::raw("CONCAT(a.nombres,' ',a.apellidos)"), 'like', '%' . $request->nombre . '%');
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
            'observacion' => 'nullable|string|max:255',
            'estado' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ($id && !DB::table('vacacional_alumnos')->where('id', $id)->exists()) {
            return response(get_response_body(['El alumno no existe.']), Response::HTTP_NOT_FOUND);
        }

        $datos = array_merge(
            array_fill_keys(self::CAMPOS, null),
            $request->only(self::CAMPOS),
            Vacacionales::auditoria(!$id)
        );
        if ($id) {
            DB::table('vacacional_alumnos')->where('id', $id)->update($datos);
        } else {
            $id = DB::table('vacacional_alumnos')->insertGetId($datos);
        }
        return $this->formatear($this->consulta()->where('a.id', $id)->first());
    }

    public function store(Request $request)
    {
        $alumno = $this->guardar($request, null);
        return is_array($alumno)
            ? response(get_response_body(['El alumno de vacacionales ha sido creado.', 2], $alumno), Response::HTTP_CREATED)
            : $alumno;
    }

    public function update(Request $request, $id)
    {
        $alumno = $this->guardar($request, (int) $id);
        return is_array($alumno)
            ? response(get_response_body(['El alumno de vacacionales ha sido modificado.', 1], $alumno), Response::HTTP_OK)
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
        DB::table('vacacional_alumnos')->where('id', $id)->delete();
        return response(get_response_body(['El alumno de vacacionales ha sido eliminado.', 3]), Response::HTTP_OK);
    }
}
