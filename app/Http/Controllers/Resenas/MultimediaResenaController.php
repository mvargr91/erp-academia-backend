<?php

namespace App\Http\Controllers\Resenas;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ArchivosService;
use Illuminate\Support\Facades\Validator;
use App\Models\Resenas\MultimediaResena;

class MultimediaResenaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'resena_id' => 'integer|required|exists:resenas,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response(MultimediaResena::obtenerColeccion($datos), Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'resena_id' => 'integer|required|exists:resenas,id',
                'ruta_archivo' => 'string|required_without:archivo|nullable|max:255',
                'archivo' => ArchivosService::REGLA_IMAGEN . '|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $datos['ruta_archivo'] = $datos['ruta_archivo'] ?? '';
            $anterior = ArchivosService::rutaActual('multimedia_resena', $datos['id'] ?? null, 'ruta_archivo');
            $media = MultimediaResena::crear($datos);
            $experiencia = DB::table('resenas')->join('experiencias', 'experiencias.id', '=', 'resenas.experiencia_id')
                ->where('resenas.id', $media->resena_id)->first(['experiencias.id', 'experiencias.slug']);
            if (ArchivosService::adjuntar($request, 'multimedia_resena', $media->id, 'ruta_archivo',
                ArchivosService::carpeta('experiencias', $experiencia->id, $experiencia->slug, "resenas/{$media->resena_id}"), $anterior)) {
                $media = MultimediaResena::find($media->id);
            }
            DB::commit();
            ArchivosService::confirmar();
            return response(
                get_response_body(['La imagen ha sido agregada a la reseña.', 2], $media),
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:multimedia_resena,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            ArchivosService::programarEliminacion(DB::table('multimedia_resena')->where('id', $id)->value('ruta_archivo'));
            $eliminado = MultimediaResena::eliminar($id);
            if ($eliminado) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['La imagen ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la imagen.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
