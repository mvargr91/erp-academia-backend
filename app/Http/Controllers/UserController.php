<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Http\Response;
use App\Enum\AccionAuditoriaEnum;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\Seguridad\InicioSesion;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Exception\RequestException;
use App\Models\Parametrizacion\ParametroCorreo;
use App\Models\Parametrizacion\ParametroConstante;

class UserController extends Controller
{
    // login de usuario en la aplicación
    public function login(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:255',
            'password' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()->all()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user) {

            if (Hash::check($request->password, $user->password)) {
                $token = $user->createToken('Laravel Password Grant Client')->accessToken;
                $response = ['token' => $token];
                return response($response, 200);
            } else {
                return response(["message" => "La contraseña no coincide."], 422);
            }
        } else {
            return response(["message" => 'El usuario no existe.'], 422);
        }
    }


    // registro de usuario
    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:18',
            'email' => 'required|string|unique:users',
            'password' => 'required|string|min:4',
        ]);
        if($validator->fails()){
            return response(['errors' => $validator->errors()->all()], 422);
        }
        $request['password']=Hash::make($request['password']);
        $request['remember_token']=Str::random(10);
        $user = User::create($request->toArray());
        $token = $user->createToken('Fundacion Password Grant Client')->accessToken;
        $response = ['token' => $token];
        return response($response, 200);
    }

    public function getToken(Request $request){
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if($validator->fails()){
            $errors = $validator->errors();
            return response([
                "messages" => $errors
            ], Response::HTTP_UNAUTHORIZED);
        }

        $http = new Client;
        $hostname = env("APP_URL");

        // var_dump($hostname);

        try{
            $user = User::where('email',$request->username)->first();
            $usuario = Usuario::where('identificacion_usuario', $request->username)->first();

            // Validar que el usuario esté activo
            if (!$usuario->estado) {
                return response([
                    "data" => null,
                    "messages" => ["El usuario no está activo."]
                ],Response::HTTP_UNAUTHORIZED);
            }

            if(isset($user)){

                $response = $http->post($hostname.'/oauth/token', [
                    'form_params' => [
                        'client_id' => env("PASSWORD_CLIENT_ID"),
                        'client_secret' => env("PASSWORD_CLIENT_SECRET"),
                        'grant_type' => 'password',
                        'username' => $request->username,
                        'password' => $request->password
                    ]
                ]);


                if($response->getStatusCode() == Response::HTTP_OK){
                    $responseBody = json_decode((string) $response->getBody(), true);
                    InicioSesion::create([
                        'usuario_id' => $user->usuario()->id,
                    ]);

                    return response($responseBody,Response::HTTP_OK);
                } else {
                    return response([
                        "messages" => ["La contraseña ingresada es inválidas."]
                    ],Response::HTTP_UNAUTHORIZED);
                }
            }else{
                return response([
                    "messages" => ["El usuario con identificación " . $request->username . " no está registrado."]
                ],Response::HTTP_UNAUTHORIZED);
            }
        }catch (RequestException $e){
            return response([
                "data" => $e->getMessage(),
                "messages" => ["La contraseña ingresada es inválida."]
            ],Response::HTTP_UNAUTHORIZED);
        }catch (Exception $e){
            return response([
                "data" => $e->getMessage(),
                "messages" => $e->getMessage()
            ],Response::HTTP_UNAUTHORIZED);
        }
    }

    public function getSession(){
        try{
            // Constantes

            $user = Auth::user();
            $rol = $user->rol();
            $usuario = $user->usuario();


            $query = DB::table('modulos')
            ->join('opciones_del_sistema', 'opciones_del_sistema.modulo_id', '=', 'modulos.id')
            ->join('permissions', 'opciones_del_sistema.id', '=', 'permissions.option_id')
            ->join('role_has_permissions', function ($join) use($rol) {
                $join->on('role_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('role_has_permissions.role_id', $rol->id);
            })
            ->select(
                'modulos.id',
                'modulos.nombre as modulo',
                'modulos.icono_menu',
                'modulos.posicion',
                'opciones_del_sistema.id AS id_opcion',
                'opciones_del_sistema.nombre AS opcion',
                'opciones_del_sistema.posicion AS posicion_opcion',
                'opciones_del_sistema.icono_menu AS icono_menu_opcion',
                'opciones_del_sistema.url',
                'opciones_del_sistema.url_ayuda',
                'permissions.id AS id_permiso',
                'permissions.name AS nombre_permiso',
                'permissions.title AS titulo_permiso',
                DB::raw('IF(role_has_permissions.permission_id IS NOT NULL, 1, 0) AS permitido')
            )
            ->where('modulos.estado','=',true)
            ->where('opciones_del_sistema.estado','=',true)
            ->orderBy('modulos.posicion', 'asc')
            ->orderBy('opciones_del_sistema.posicion', 'asc')
            ->get();
            $permisosPorModuloDto = [];
            $permisosPorModulo = $query->groupBy('modulo');
            foreach ($permisosPorModulo as $modulo => $permisosDelModulo){
                $permisosPorOpcionDto = [];
                $permisosPorOpcion = $permisosDelModulo->groupBy('opcion');
                foreach ($permisosPorOpcion as $opcion => $permisosOpcion){
                    $permisosDto = [];
                    foreach ($permisosOpcion as $permiso){
                        array_push($permisosDto, [
                            "nombre" => $permiso->nombre_permiso,
                            "titulo" => $permiso->titulo_permiso,
                            "permitido" => !!$permiso->permitido,
                            "id" => $permiso->id_permiso,
                        ]);
                    }

                    array_push($permisosPorOpcionDto, [
                        "id" => $permisosOpcion[0]->id_opcion,
                        "nombre" => $opcion,
                        "posicion" => $permisosOpcion[0]->posicion_opcion,
                        "icono_menu" => $permisosOpcion[0]->icono_menu_opcion,
                        "url" => $permisosOpcion[0]->url,
                        "url_ayuda" => $permisosOpcion[0]->url_ayuda,
                        "type"=>'item',
                        "permisos" => $permisosDto,
                    ]);
                }

                array_push($permisosPorModuloDto, [
                    "id" => $permisosDelModulo[0]->id,
                    "nombre" => $modulo,
                    "posicion" => $permisosDelModulo[0]->posicion,
                    "icono_menu" => $permisosDelModulo[0]->icono_menu,
                    "type"=>'collapse',
                    "opciones" => $permisosPorOpcionDto,
                ]);
            }

            return response([
                'usuario' => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'correo_electronico' => $usuario->correo_electronico,
                    'identificacion_usuario' => $usuario->identificacion_usuario,
                    'rol' => [
                        'id'=>$rol->id,
                        'nombre' => $rol->name,
                        'tipo' => $rol->type,
                    ],
                    'permisos' =>$permisosPorModuloDto,
                ]
            ], Response::HTTP_OK);
        }catch (Exception $e){
            return response($e, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Revoke user tokens
     */
    public function logout(){
        try{
            $user = Auth::user();
            $userTokens = $user->tokens;
            foreach($userTokens as $token) {
                $token->revoke();
            }
            return response(null, Response::HTTP_OK);
        }catch (Exception $e){
            return response([
                "messages" => "Ocurrió un error al intentar cerrar la sesión."
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    // Enviar correo de recuperación de contraseña
    public function forgotPassword(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:usuarios,correo_electronico'
        ], [
            'exists' => 'El usuario ' . $request->email . ' no existe.'
        ]);

        if ($validator->fails()) {
            return response(['mensajes' => $validator->errors()->all()], Response::HTTP_BAD_REQUEST);
        }

        $usuario = Usuario::where('correo_electronico', $request->email)->first();

        if (!$usuario) {
            return response(["mensajes" => ['No se encontró el usuario en la base de datos.']], Response::HTTP_BAD_REQUEST);
        }

        $user = User::where('email', $usuario->identificacion_usuario)->first();

        if (!$user) {
            return response(["mensajes" => ['No se encontró un usuario asociado en la tabla users.']], Response::HTTP_BAD_REQUEST);
        }

        // Generar un token de recuperación de contraseña
        $token = Password::createToken($user);

        if (!$token) {
            return response(["mensajes" => ['Problema con el servidor de correos']], Response::HTTP_BAD_REQUEST);
        }

        $parametros = ParametroConstante::cargarParametros();

        if (!isset($parametros['ID_CORREO_CAMBIO_CLAVE'])) {
            return response(["mensajes" => ['El parámetro ID_CORREO_CAMBIO_CLAVE no existe']], Response::HTTP_BAD_REQUEST);
        }

        $parametroCorreo = ParametroCorreo::find($parametros['ID_CORREO_CAMBIO_CLAVE']);

        if (!isset($parametroCorreo)) {
            return response(["mensajes" => ['El parámetro ID_CORREO_CAMBIO_CLAVE es inválido']], Response::HTTP_BAD_REQUEST);
        }

        $parametroCorreo->texto = str_replace('&amp;1', $usuario->nombre, $parametroCorreo->texto);
        $parametroCorreo->texto = str_replace('&amp;2', env('APP_FRONT_URL') . '/reset-password/' . $token, $parametroCorreo->texto);

        Mail::send('emails.reset-password', ['texto' => $parametroCorreo->texto], function (Message $message) use ($user, $usuario, $parametroCorreo) {
            $message->subject($parametroCorreo->asunto);
            $message->to($usuario->correo_electronico);
        });

        return response(["mensajes" => ["El email ha sido enviado"]], Response::HTTP_OK);
    }

    // Restablecer la contraseña
    public function resetPassword(Request $request){
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|exists:usuarios,correo_electronico',
            'password' => 'required|confirmed|min:6',
        ], [
            'email.exists' => 'El usuario no existe.',
            'token.required' => 'El token es inválido.',
        ]);

        if ($validator->fails()) {
            return response(['mensajes' => $validator->errors()->all()], Response::HTTP_BAD_REQUEST);
        }

        $usuario = Usuario::where('correo_electronico', $request->email)->first();

        if (!$usuario) {
            return response(["mensajes" => ['No se encontró el usuario en la base de datos.']], Response::HTTP_BAD_REQUEST);
        }

        $user = User::where('email', $usuario->identificacion_usuario)->first();

        if (!$user || !Password::tokenExists($user, $request->token)) {
            return response(["mensajes" => ['Token inválido o expirado']], Response::HTTP_BAD_REQUEST);
        }

        // Restablecer la contraseña
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        Password::deleteToken($user);
        event(new PasswordReset($user));

        // Registrar auditoría
        $auditoriaDto = [
            'id_recurso' => $usuario->id,
            'nombre_recurso' => Usuario::class,
            'descripcion_recurso' => $usuario->nombre,
            'accion' => 'Cambio de contraseña',
            'recurso_original' => $usuario->toJson(),
            'recurso_resultante' => $usuario->toJson(),
            'responsable_id' => $usuario->id,
            'responsable_nombre' => $usuario->nombre,
        ];

        AuditoriaTabla::create($auditoriaDto);

        return response(["mensajes" => ["La contraseña ha sido actualizada"]], Response::HTTP_OK);
    }
}
