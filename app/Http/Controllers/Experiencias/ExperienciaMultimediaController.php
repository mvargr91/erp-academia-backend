<?php

namespace App\Http\Controllers\Experiencias;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ArchivosService;
use App\Http\Controllers\Concerns\AlcancePorRol;
use Illuminate\Support\Facades\Validator;
use App\Models\Experiencias\ExperienciaMultimedia;

class ExperienciaMultimediaController extends Controller
{
    use AlcancePorRol;

    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'experiencia_id' => 'integer|required|exists:experiencias,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->experienciaEnAlcance($datos['experiencia_id'])) {
                return $this->respuestaSinAcceso();
            }

            return response(ExperienciaMultimedia::obtenerColeccion($datos), Response::HTTP_OK);
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
                'experiencia_id' => 'integer|required|exists:experiencias,id',
                'tipo' => 'string|required|in:foto,video',
                'ruta_archivo' => 'string|required_without:archivo|nullable|max:255',
                'archivo' => (($datos['tipo'] ?? '') === 'video' ? ArchivosService::REGLA_VIDEO : ArchivosService::REGLA_IMAGEN) . '|nullable',
                'titulo' => 'string|nullable|max:150',
                'texto_alternativo' => 'string|nullable|max:255',
                'orden' => 'integer|nullable',
                'estado' => 'boolean|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->experienciaEnAlcance($datos['experiencia_id'])) {
                return $this->respuestaSinAcceso();
            }

            $datos['ruta_archivo'] = $datos['ruta_archivo'] ?? '';
            if (!$request->hasFile('archivo') && isset($datos['id']) && $datos['ruta_archivo'] === '') {
                unset($datos['ruta_archivo']); // al modificar sin archivo nuevo se conserva el actual
            }
            $anterior = ArchivosService::rutaActual('experiencia_multimedia', $datos['id'] ?? null, 'ruta_archivo');
            $media = ExperienciaMultimedia::modificarOCrear($datos);
            $experiencia = DB::table('experiencias')->where('id', $media->experiencia_id)->first(['id', 'slug']);
            if (ArchivosService::adjuntar($request, 'experiencia_multimedia', $media->id, 'ruta_archivo',
                ArchivosService::carpeta('experiencias', $experiencia->id, $experiencia->slug, $media->tipo === 'video' ? 'videos' : 'fotos'), $anterior)) {
                $media = ExperienciaMultimedia::cargar($media->id);
            }
            DB::commit();
            ArchivosService::confirmar();
            return response(
                get_response_body(['La multimedia ha sido creada.', 2], $media),
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:experiencia_multimedia,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_multimedia', $id)) {
                return $this->respuestaSinAcceso();
            }

            return response(ExperienciaMultimedia::cargar($id), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:experiencia_multimedia,id',
                'tipo' => 'string|required|in:foto,video',
                'ruta_archivo' => 'string|nullable|max:255',
                'archivo' => (($datos['tipo'] ?? '') === 'video' ? ArchivosService::REGLA_VIDEO : ArchivosService::REGLA_IMAGEN) . '|nullable',
                'titulo' => 'string|nullable|max:150',
                'texto_alternativo' => 'string|nullable|max:255',
                'orden' => 'integer|nullable',
                'estado' => 'boolean|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_multimedia', $id)) {
                return $this->respuestaSinAcceso();
            }

            $datos['ruta_archivo'] = $datos['ruta_archivo'] ?? '';
            if (!$request->hasFile('archivo') && isset($datos['id']) && $datos['ruta_archivo'] === '') {
                unset($datos['ruta_archivo']); // al modificar sin archivo nuevo se conserva el actual
            }
            $anterior = ArchivosService::rutaActual('experiencia_multimedia', $datos['id'] ?? null, 'ruta_archivo');
            $media = ExperienciaMultimedia::modificarOCrear($datos);
            $experiencia = DB::table('experiencias')->where('id', $media->experiencia_id)->first(['id', 'slug']);
            if (ArchivosService::adjuntar($request, 'experiencia_multimedia', $media->id, 'ruta_archivo',
                ArchivosService::carpeta('experiencias', $experiencia->id, $experiencia->slug, $media->tipo === 'video' ? 'videos' : 'fotos'), $anterior)) {
                $media = ExperienciaMultimedia::cargar($media->id);
            }
            DB::commit();
            ArchivosService::confirmar();
            return response(
                get_response_body(['La multimedia ha sido modificada.', 1], $media),
                Response::HTTP_OK
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
                'id' => 'integer|required|exists:experiencia_multimedia,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_multimedia', $id)) {
                return $this->respuestaSinAcceso();
            }

            ArchivosService::programarEliminacion(DB::table('experiencia_multimedia')->where('id', $id)->value('ruta_archivo'));
            $eliminado = ExperienciaMultimedia::eliminar($id);
            if ($eliminado) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['La multimedia ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la multimedia.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
