<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Support\Facades\Validator;

/**
 * Condonar la deuda de un alumno en un curso o en un paquete: el saldo queda en 0 sin registrar
 * un pago (no entra dinero, no suma a los ingresos). Se puede deshacer: el saldo vuelve.
 */
class CondonacionController extends Controller
{
    /** POST /v1/condonaciones { alumno_id, curso_id | paquete_id, motivo } → condona todo el saldo pendiente. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'alumno_id' => 'integer|required|exists:alumnos,id',
            'curso_id' => 'integer|nullable|required_without:paquete_id|exists:cursos,id',
            'paquete_id' => 'integer|nullable|exists:paquetes_alumno,id',
            'motivo' => 'string|required|max:255',
        ], [
            'curso_id.required_without' => 'Elige el curso o el paquete cuya deuda se condona.',
            'motivo.required' => 'Escribe el motivo de la condonación.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        try {
            return DB::transaction(function () use ($request) {
                $paqueteId = $request->filled('paquete_id') ? (int) $request->paquete_id : null;
                $cursoId = $paqueteId ? null : (int) $request->curso_id;
                $deuda = $this->deuda((int) $request->alumno_id, $cursoId, $paqueteId)->lockForUpdate()->first();
                if (!$deuda) {
                    return response(get_response_body([$paqueteId ? 'El paquete no es de este alumno.' : 'El alumno no está matriculado en ese curso.']), Response::HTTP_CONFLICT);
                }
                $saldo = (float) $deuda->saldo;
                if ($saldo <= 0) {
                    return response(get_response_body(['No hay saldo pendiente para condonar.']), Response::HTTP_CONFLICT);
                }

                $usuario = Auth::user()->usuario();
                $id = DB::table('condonaciones')->insertGetId([
                    'alumno_id' => $request->alumno_id,
                    'curso_id' => $cursoId,
                    'paquete_id' => $paqueteId,
                    'valor' => $saldo,
                    'motivo' => trim($request->motivo),
                    'usuario_creacion_id' => $usuario->id ?? null,
                    'usuario_creacion_nombre' => $usuario->nombre ?? null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $this->deuda((int) $request->alumno_id, $cursoId, $paqueteId)->update(['saldo' => 0, 'updated_at' => Carbon::now()]);
                $this->auditar($id, AccionAuditoriaEnum::CREAR);

                return response(get_response_body(
                    ['Se condonó la deuda de $' . number_format($saldo, 0, ',', '.') . '. El saldo quedó en 0.', 1],
                    ['id' => $id, 'valor' => $saldo]
                ), Response::HTTP_CREATED);
            });
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** DELETE /v1/condonaciones/{id}: deshace la condonación; el valor vuelve al saldo. */
    public function destroy($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $c = DB::table('condonaciones')->where('id', $id)->lockForUpdate()->first();
                if (!$c) {
                    return response(get_response_body(['La condonación no existe.']), Response::HTTP_NOT_FOUND);
                }
                $this->auditar((int) $id, AccionAuditoriaEnum::ELIMINAR);
                if ($c->curso_id || $c->paquete_id) {
                    $this->deuda((int) $c->alumno_id, $c->curso_id, $c->paquete_id)
                        ->update(['saldo' => DB::raw('saldo + ' . (float) $c->valor), 'updated_at' => Carbon::now()]);
                }
                DB::table('condonaciones')->where('id', $id)->delete();
                return response(get_response_body(['Se deshizo la condonación: el valor volvió al saldo del alumno.', 1]), Response::HTTP_OK);
            });
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Fila que lleva el saldo: el paquete del alumno o su matrícula en el curso. */
    private function deuda(int $alumnoId, ?int $cursoId, ?int $paqueteId)
    {
        return $paqueteId
            ? DB::table('paquetes_alumno')->where('id', $paqueteId)->where('alumno_id', $alumnoId)
            : DB::table('curso_alumno')->where('curso_id', $cursoId)->where('alumno_id', $alumnoId);
    }

    private function auditar(int $id, $accion): void
    {
        $c = DB::table('condonaciones')->where('id', $id)->first();
        AuditoriaTabla::crear([
            'id_recurso' => $id,
            'nombre_recurso' => 'Condonacion',
            'descripcion_recurso' => "Condonación #{$id} del alumno #{$c->alumno_id}",
            'accion' => $accion,
            'recurso_original' => json_encode($c),
            'recurso_resultante' => null,
        ]);
    }
}
