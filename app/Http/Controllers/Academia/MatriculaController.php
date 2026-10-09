<?php

namespace App\Http\Controllers\Academia;

use Exception;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Support\CorreoUnico;
use App\Services\Academia\Matriculas;
use Illuminate\Support\Facades\Validator;

/** Matrícula rápida: alumno (nuevo o existente) + uno o varios cursos + pago, en un solo paso. */
class MatriculaController extends Controller
{
    // Nombres legibles de los campos anidados en los mensajes de validación.
    private const ATRIBUTOS = [
        'alumno_id' => 'alumno',
        'alumno.nombres' => 'nombres',
        'alumno.apellidos' => 'apellidos',
        'alumno.documento' => 'documento',
        'alumno.telefono' => 'teléfono',
        'alumno.correo' => 'correo',
        'alumno.fecha_nacimiento' => 'fecha de nacimiento',
        'alumno.sede_id' => 'sede',
        'cursos' => 'cursos',
        'cursos.*' => 'curso',
        'pago.monto' => 'monto',
        'pago.fecha_pago' => 'fecha de pago',
        'pago.metodo_pago' => 'método de pago',
        'pago.sede_id' => 'sede del pago',
        'pago.referencia' => 'referencia',
    ];

    private function reglasCursos()
    {
        return [
            'alumno_id' => 'integer|nullable|exists:alumnos,id',
            'cursos' => 'array|required|min:1',
            'cursos.*' => 'integer|distinct|exists:cursos,id',
            // curso_id => alumno con quien paga ese curso en pareja
            'parejas' => 'array|nullable',
            'parejas.*' => 'integer|nullable|exists:alumnos,id',
            'ajustar_pareja' => 'boolean|nullable',
        ];
    }

    /** Precio de cada curso y total, sin guardar. POST /v1/matriculas/cotizar */
    public function cotizar(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, $this->reglasCursos(), [], self::ATRIBUTOS);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }
            return response(
                Matriculas::cotizar(isset($datos['alumno_id']) ? (int) $datos['alumno_id'] : null, $datos['cursos'], $datos['parejas'] ?? []),
                Response::HTTP_OK
            );
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, array_merge($this->reglasCursos(), [
                'alumno' => 'array|nullable|required_without:alumno_id',
                'alumno.sede_id' => 'integer|nullable|exists:sedes,id',
                'alumno.nombres' => 'string|required_without:alumno_id|max:100',
                'alumno.apellidos' => 'string|required_without:alumno_id|max:100',
                'alumno.documento' => 'string|nullable|max:30',
                'alumno.telefono' => 'string|nullable|max:30',
                'alumno.correo' => 'email|nullable|max:150',
                'alumno.fecha_nacimiento' => 'date|nullable',
                'alumno.direccion' => 'string|nullable|max:255',
                'alumno.contacto_emergencia' => 'string|nullable|max:150',
                'alumno.telefono_emergencia' => 'string|nullable|max:30',
                'pago' => 'array|nullable',
                'pago.monto' => 'numeric|required_with:pago|gt:0',
                'pago.fecha_pago' => 'date|required_with:pago',
                'pago.metodo_pago' => 'string|required_with:pago|in:efectivo,transferencia,tarjeta,otro',
                'pago.sede_id' => 'integer|nullable|exists:sedes,id',
                'pago.referencia' => 'string|nullable|max:100',
                'pago.observacion' => 'string|nullable',
            ]), [], self::ATRIBUTOS);
            if (empty($datos['alumno_id'])) {
                CorreoUnico::validar($validator, 'alumnos', $datos['alumno']['correo'] ?? null, null, 'alumno.correo');
            }
            if ($validator->fails()) {
                DB::rollback();
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            $resultado = Matriculas::registrar($datos);
            DB::commit();
            return response(get_response_body(['La matrícula ha sido registrada.', 2], $resultado), Response::HTTP_CREATED);
        } catch (DomainException $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
