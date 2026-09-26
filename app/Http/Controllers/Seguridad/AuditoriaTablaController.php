<?php

namespace App\Http\Controllers\Seguridad;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Support\Facades\Validator;

class AuditoriaTablaController extends Controller
{
    public function index(Request $request)
    {
        try{
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'limite' => 'integer|between:1,500'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            if(isset($datos['ordenar_por'])){
                $datos['ordenar_por'] = format_order_by_attributes($datos);
            }
            $auditoria = AuditoriaTabla::obtenerColeccion($datos);
            return response($auditoria, Response::HTTP_OK);
        }catch(Exception $e){
            return response($e->getTraceAsString(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
