<?php

namespace App\Http\Controllers\Seguridad;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Seguridad\AuditoriaTabla;
use App\Models\Seguridad\Usuario;
use App\Models\Proveedores\ProveedorTuristico;

/**
 * "Mi cuenta": cada usuario consulta y modifica solo sus propios datos.
 * No recibe ids: todo se resuelve desde el token, así ningún rol puede tocar la cuenta de otro.
 * La identificación no se modifica porque es el usuario de ingreso (users.email).
 */
class CuentaController extends Controller
{
    private const CAMPOS_PROVEEDOR = [
        'nombre_comercial', 'razon_social', 'nit', 'descripcion', 'telefono', 'correo',
        'direccion', 'destino_id', 'sitio_web', 'instagram', 'facebook', 'rnt',
    ];

    public function show()
    {
        try {
            return response($this->datosCuenta(Auth::user()), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        $validator = Validator::make($request->all(), [
            'nombre' => 'string|required|max:128',
            'correo_electronico' => [
                'email', 'required', 'max:128',
                Rule::unique('usuarios', 'correo_electronico')->ignore($usuario->id),
            ],
        ], [
            'correo_electronico.unique' => 'El correo electrónico ya está registrado.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $datos = $validator->validated();
            DB::table('usuarios')->where('id', $usuario->id)->update([
                'nombre' => $datos['nombre'],
                'correo_electronico' => $datos['correo_electronico'],
                'usuario_modificacion_id' => $usuario->id,
                'usuario_modificacion_nombre' => $datos['nombre'],
                'updated_at' => now(),
            ]);
            $user->name = $datos['nombre'];
            $user->save();

            AuditoriaTabla::crear([
                'id_recurso' => $usuario->id,
                'nombre_recurso' => Usuario::class,
                'descripcion_recurso' => $datos['nombre'],
                'accion' => AccionAuditoriaEnum::MODIFICAR,
                'recurso_original' => json_encode($usuario),
                'recurso_resultante' => json_encode($datos),
            ]);

            DB::commit();
            return response(
                get_response_body(['Tus datos fueron actualizados.', 1], $this->datosCuenta($user->fresh())),
                Response::HTTP_OK
            );
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function cambiarClave(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'clave_actual' => 'string|required',
            'clave' => 'string|required|min:8|max:32|confirmed|different:clave_actual',
        ], [
            'clave.confirmed' => 'Las contraseñas no coinciden.',
            'clave.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if (!Hash::check($request->input('clave_actual'), $user->password)) {
            return response(get_response_body(['La contraseña actual no es correcta.']), Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $user->password = Hash::make($request->input('clave'));
            $user->save();

            $usuario = $user->usuario();
            AuditoriaTabla::crear([
                'id_recurso' => $usuario->id,
                'nombre_recurso' => Usuario::class,
                'descripcion_recurso' => $usuario->nombre,
                'accion' => AccionAuditoriaEnum::CAMBIO_CONTRASENA,
                'recurso_original' => json_encode(['usuario_id' => $usuario->id]),
            ]);

            DB::commit();
            return response(get_response_body(['Tu contraseña fue actualizada.', 1]), Response::HTTP_OK);
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Datos del negocio del proveedor (equivale al formulario "Únete como proveedor").
     * El estado de verificación no se toca: lo decide el administrador.
     */
    public function actualizarProveedor(Request $request)
    {
        $usuario = Auth::user()->usuario();
        $proveedor = DB::table('proveedores_turisticos')->where('usuario_id', $usuario->id)->first();
        if (!$proveedor) {
            return response(get_response_body(['Tu cuenta no tiene un negocio vinculado.']), Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'nombre_comercial' => 'string|required|max:150',
            'razon_social' => 'nullable|string|max:150',
            'nit' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'telefono' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:128',
            'direccion' => 'nullable|string|max:255',
            'destino_id' => 'nullable|integer|exists:destinos,id',
            'sitio_web' => 'nullable|url|max:255',
            'instagram' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            'rnt' => 'nullable|string|max:50',
        ], [
            'sitio_web.url' => 'El sitio web debe ser una URL válida (https://...).',
            'correo.email' => 'El correo de contacto no es válido.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $datos = array_intersect_key($validator->validated(), array_flip(self::CAMPOS_PROVEEDOR));
            DB::table('proveedores_turisticos')->where('id', $proveedor->id)->update(array_merge($datos, [
                'usuario_modificacion_id' => $usuario->id,
                'usuario_modificacion_nombre' => $usuario->nombre,
                'updated_at' => now(),
            ]));

            AuditoriaTabla::crear([
                'id_recurso' => $proveedor->id,
                'nombre_recurso' => ProveedorTuristico::class,
                'descripcion_recurso' => $datos['nombre_comercial'],
                'accion' => AccionAuditoriaEnum::MODIFICAR,
                'recurso_original' => json_encode($proveedor),
                'recurso_resultante' => json_encode($datos),
            ]);

            DB::commit();
            return response(
                get_response_body(['Los datos de tu negocio fueron actualizados.', 1], $this->datosCuenta(Auth::user())),
                Response::HTTP_OK
            );
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function datosCuenta(User $user): array
    {
        $usuario = $user->usuario();
        $rol = $user->rol();

        $proveedor = DB::table('proveedores_turisticos')
            ->leftJoin('destinos', 'destinos.id', '=', 'proveedores_turisticos.destino_id')
            ->where('proveedores_turisticos.usuario_id', $usuario->id)
            ->select(array_merge(
                array_map(fn ($campo) => "proveedores_turisticos.$campo", self::CAMPOS_PROVEEDOR),
                [
                    'proveedores_turisticos.id',
                    'proveedores_turisticos.estado_verificacion',
                    'proveedores_turisticos.observaciones_verificacion',
                    'proveedores_turisticos.verificado_en',
                    'destinos.nombre AS destino',
                ]
            ))
            ->first();

        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'identificacion_usuario' => $usuario->identificacion_usuario,
            'correo_electronico' => $usuario->correo_electronico,
            'rol' => $rol?->name,
            'miembro_desde' => $usuario->created_at,
            'proveedor' => $proveedor,
        ];
    }
}
