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

/**
 * "Mi cuenta": cada usuario consulta y modifica solo sus propios datos.
 * No recibe ids: todo se resuelve desde el token, así ningún rol puede tocar la cuenta de otro.
 * La identificación no se modifica porque es el usuario de ingreso (users.email).
 */
class CuentaController extends Controller
{
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

    private function datosCuenta(User $user): array
    {
        $usuario = $user->usuario();
        $rol = $user->rol();

        return [
            'id' => $usuario->id,
            'nombre' => $usuario->nombre,
            'identificacion_usuario' => $usuario->identificacion_usuario,
            'correo_electronico' => $usuario->correo_electronico,
            'rol' => $rol?->name,
            'miembro_desde' => $usuario->created_at,
        ];
    }
}
