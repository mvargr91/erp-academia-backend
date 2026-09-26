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
use App\Models\Experiencias\Experiencia;
use App\Models\Experiencias\ExperienciaPrecio;

class ExperienciaController extends Controller
{
    use AlcancePorRol;

    public function index(Request $request)
    {
        try {
            $datos = $this->aplicarAlcance($request->all(), 'proveedor_id', null);
            if (!$request->ligera) {
                $validator = Validator::make($datos, [
                    'limite' => 'integer|between:1,500'
                ]);

                if ($validator->fails()) {
                    return response(
                        get_response_body(format_messages_validator($validator)),
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            if ($request->ligera) {
                $experiencias = Experiencia::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $experiencias = Experiencia::obtenerColeccion($datos);
            }
            return response($experiencias, Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $this->aplicarAlcance($request->all(), 'proveedor_id', null);
            $validator = Validator::make($datos, [
                'proveedor_id' => 'integer|required|exists:proveedores_turisticos,id',
                'destino_id' => 'integer|required|exists:destinos,id',
                'nombre' => 'string|required|max:180',
                'slug' => 'string|required|max:200|unique:experiencias,slug',
                'idioma' => 'string|nullable|max:50',
                'precio_desde' => 'numeric|nullable',
                'capacidad_maxima' => 'integer|nullable',
                'estado' => 'string|nullable|max:30',
                'destacada' => 'boolean',
                'verificada' => 'boolean',
                'precios' => 'array|required|min:1',
            ] + ExperienciaPrecio::reglas('precios.*.'), [
                'precios.required' => 'La experiencia necesita al menos un precio.',
                'precios.min' => 'La experiencia necesita al menos un precio.',
            ] + ExperienciaPrecio::mensajes('precios.*.'));

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }
            if (isset($datos['precios']) && ($error = ExperienciaPrecio::validarCantidadesUnicas($datos['precios']))) {
                return response(get_response_body([$error]), Response::HTTP_BAD_REQUEST);
            }
            // El "precio desde" se calcula a partir de los precios.
            unset($datos['precio_desde']);

            // El proveedor no puede autodestacarse ni autoverificarse; el estado va por cambiarEstado.
            if ($this->esProveedor()) {
                unset($datos['destacada'], $datos['verificada']);
                $datos['estado'] = 'borrador';
            }

            $experiencia = Experiencia::modificarOCrear($datos);
            if ($experiencia && isset($datos['precios'])) {
                ExperienciaPrecio::sincronizar($experiencia['id'], $datos['precios']);
                $experiencia = Experiencia::cargar($experiencia['id']);
            }

            if ($experiencia) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['La experiencia ha sido creada.', 2], $experiencia),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar crear la experiencia.']), Response::HTTP_CONFLICT);
            }
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
                'id' => 'integer|required|exists:experiencias,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->experienciaEnAlcance($id)) {
                return $this->respuestaSinAcceso();
            }

            return response(Experiencia::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:experiencias,id',
                'proveedor_id' => 'integer|required|exists:proveedores_turisticos,id',
                'destino_id' => 'integer|required|exists:destinos,id',
                'nombre' => 'string|required|max:180',
                'slug' => 'string|required|max:200|unique:experiencias,slug,' . $id,
                'idioma' => 'string|nullable|max:50',
                'precio_desde' => 'numeric|nullable',
                'capacidad_maxima' => 'integer|nullable',
                'estado' => 'string|nullable|max:30',
                'destacada' => 'boolean',
                'verificada' => 'boolean',
                'precios' => 'array|sometimes|min:1',
            ] + ExperienciaPrecio::reglas('precios.*.'), [
                'precios.min' => 'La experiencia necesita al menos un precio.',
            ] + ExperienciaPrecio::mensajes('precios.*.'));

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }
            if (isset($datos['precios']) && ($error = ExperienciaPrecio::validarCantidadesUnicas($datos['precios']))) {
                return response(get_response_body([$error]), Response::HTTP_BAD_REQUEST);
            }
            // El "precio desde" se calcula a partir de los precios.
            unset($datos['precio_desde']);

            if (!$this->experienciaEnAlcance($id)) {
                return $this->respuestaSinAcceso();
            }
            // El proveedor no puede autodestacarse ni autoverificarse; el estado va por cambiarEstado.
            if ($this->esProveedor()) {
                unset($datos['destacada'], $datos['verificada']);
                unset($datos['estado']);
                $datos['proveedor_id'] = $this->proveedorActualId();
            }

            $experiencia = Experiencia::modificarOCrear($datos);
            if ($experiencia && isset($datos['precios'])) {
                ExperienciaPrecio::sincronizar($experiencia['id'], $datos['precios']);
                $experiencia = Experiencia::cargar($experiencia['id']);
            }
            if ($experiencia) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['La experiencia ha sido modificada.', 1], $experiencia),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar modificar la experiencia.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function cambiarEstado(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:experiencias,id',
                'estado' => 'string|required|in:borrador,en_revision,aprobada,publicada,rechazada,suspendida,archivada',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->experienciaEnAlcance($id)) {
                return $this->respuestaSinAcceso();
            }
            // El proveedor solo envía a revisión o archiva; aprobar/publicar es del administrador.
            if ($this->esProveedor() && !in_array($datos['estado'], ['borrador', 'en_revision', 'archivada'])) {
                return $this->respuestaSinAcceso();
            }

            $experiencia = Experiencia::cambiarEstado($id, $datos['estado']);
            DB::commit();
            ArchivosService::confirmar();
            return response(
                get_response_body(['El estado de la experiencia ha sido actualizado.', 1], $experiencia),
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
                'id' => 'integer|required|exists:experiencias,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->experienciaEnAlcance($id)) {
                return $this->respuestaSinAcceso();
            }

            ArchivosService::programarEliminacionCarpeta('experiencias', $id);
            $eliminado = Experiencia::eliminar($id);
            if ($eliminado) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['La experiencia ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la experiencia.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
