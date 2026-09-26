<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\ProyectoFoto;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProyectoFotoController extends Controller
{
   /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        try{
            $datos = $request->all();
            if(!$request->ligera){
                $validator = Validator::make($datos, [
                    'limite' => 'integer|between:1,500',
                ]);

                if($validator->fails()) {
                    return response(
                        get_response_body(format_messages_validator($validator))
                        , Response::HTTP_BAD_REQUEST
                    );
                }
            }

            if($request->ligera){
                $ProyectoFoto = ProyectoFoto::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $ProyectoFoto = ProyectoFoto::obtenerColeccion($datos);
            }
            return response($ProyectoFoto, Response::HTTP_OK);
        }catch(Exception $e){
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction(); // Se abre la transacción
        try {
            $datos = $request->all();

            $validator = Validator::make($datos, [
                'id_proyecto' => 'integer|required|exists:proyectos,id',
                'nombre_foto' => 'string|required|max:128',
                'archivo'     => 'file|required|mimes:jpg,jpeg,png,webp|max:5120', // 5MB
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $idProyecto = $datos['id_proyecto'];

            if ($request->hasFile('archivo')) {
                $archivo = $request->file('archivo');
                $nombreArchivo = time() . '_' . $archivo->getClientOriginalName();
                $rutaAlmacenamiento = "proyectos/{$idProyecto}/foto/{$nombreArchivo}";
                $archivo->storeAs("public/proyectos/{$idProyecto}/foto", $nombreArchivo);
            } else {
                return response(
                    get_response_body(["No se ha recibido ningún archivo."]),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $datos['nombre_archivo_foto'] = $nombreArchivo;
            $datos['ruta_archivo_foto']   = $rutaAlmacenamiento;

            $ProyectoFoto = ProyectoFoto::modificarOCrear($datos);

            if ($ProyectoFoto) {
                DB::commit(); 
                return response(
                    get_response_body(["La foto proyecto ha sido creada.", 2], $ProyectoFoto),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(
                    get_response_body(["Ocurrió un error al intentar crear la foto proyecto."]),
                    Response::HTTP_CONFLICT
                );
            }
        } catch (Exception $e) {
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(
                get_response_body([$e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try{
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:proyectos_fotos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(ProyectoFoto::cargar($id), Response::HTTP_OK);
        }catch (Exception $e){
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction(); // Se abre la transacción
        try {
            $datos = $request->all();
            $datos['id'] = $id;

            $validator = Validator::make($datos, [
                'id'          => 'integer|required|exists:proyectos_fotos,id',
                'id_proyecto' => 'integer|required|exists:proyectos,id',
                'nombre_foto' => 'string|required|max:128',
                'archivo'     => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Foto actual en BD
            $fotoActual = ProyectoFoto::findOrFail($id);

            // Por defecto, conservar lo que ya hay
            $datos['nombre_archivo_foto'] = $fotoActual->nombre_archivo_foto ?? null;
            if (property_exists($fotoActual, 'ruta_archivo_foto')) {
                $datos['ruta_archivo_foto'] = $fotoActual->ruta_archivo_foto ?? null;
            }

            // Si viene archivo nuevo, lo guardamos y opcionalmente borramos el anterior
            if ($request->hasFile('archivo')) {
                $archivo     = $request->file('archivo');
                $idProyecto  = $datos['id_proyecto']; // o $fotoActual->id_proyecto si lo prefieres fijo

                $nombreArchivo      = time() . '_' . $archivo->getClientOriginalName();
                $rutaAlmacenamiento = "proyectos/{$idProyecto}/foto/{$nombreArchivo}";

                // Guardar nuevo archivo
                $archivo->storeAs("public/proyectos/{$idProyecto}/foto", $nombreArchivo);

                // Borrar archivo anterior (si existe)
                $rutaAnterior = null;

                if (!empty($fotoActual->ruta_archivo_foto)) {
                    // Si guardas la ruta relativa en BD (proyectos/{id}/foto/archivo.jpg)
                    $rutaAnterior = 'public/' . $fotoActual->ruta_archivo_foto;
                } elseif (!empty($fotoActual->nombre_archivo_foto) && !empty($fotoActual->id_proyecto)) {
                    // Fallback si solo guardas nombre + id_proyecto
                    $rutaAnterior = "public/proyectos/{$fotoActual->id_proyecto}/foto/{$fotoActual->nombre_archivo_foto}";
                }

                if ($rutaAnterior && Storage::exists($rutaAnterior)) {
                    Storage::delete($rutaAnterior);
                }

                // Actualizar datos para guardar en BD
                $datos['nombre_archivo_foto'] = $nombreArchivo;
                if (array_key_exists('ruta_archivo_foto', $datos)) {
                    $datos['ruta_archivo_foto'] = $rutaAlmacenamiento;
                }
            }

            $ProyectoFoto = ProyectoFoto::modificarOCrear($datos);

            if ($ProyectoFoto) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La foto proyecto ha sido modificada.", 1], $ProyectoFoto),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(
                    get_response_body(["Ocurrió un error al intentar modificar la foto proyecto."]),
                    Response::HTTP_CONFLICT
                );
            }
        } catch (Exception $e) {
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(
                get_response_body([$e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction(); // Se abre la transacción
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:proyectos_fotos,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Cargar la foto para obtener ruta y/o nombre
            $foto = ProyectoFoto::find($id);

            if (!$foto) {
                return response(
                    get_response_body(["La foto proyecto no existe."]),
                    Response::HTTP_NOT_FOUND
                );
            }

            // Guarda la ruta antes de eliminar en BD
            $rutaArchivo = null;

            // Si guardas 'ruta_archivo_foto' completa (proyectos/{id}/foto/archivo.jpg)
            if (!empty($foto->ruta_archivo_foto)) {
                $rutaArchivo = 'public/' . $foto->ruta_archivo_foto;
            } elseif (!empty($foto->nombre_archivo_foto) && !empty($foto->id_proyecto)) {
                // Fallback si solo guardas nombre + id_proyecto
                $rutaArchivo = "public/proyectos/{$foto->id_proyecto}/foto/{$foto->nombre_archivo_foto}";
            }

            $eliminado = ProyectoFoto::eliminar($id);

            if ($eliminado) {
                // Si se eliminó en BD, intentamos borrar el archivo físico
                if ($rutaArchivo && Storage::exists($rutaArchivo)) {
                    Storage::delete($rutaArchivo);
                }

                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La foto proyecto ha sido eliminada.", 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(
                    get_response_body(["Ocurrió un error al intentar eliminar la foto proyecto."]),
                    Response::HTTP_CONFLICT
                );
            }
        } catch (Exception $e) {
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(
                get_response_body([$e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }


 
}
