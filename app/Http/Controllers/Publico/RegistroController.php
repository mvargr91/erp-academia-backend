<?php

namespace App\Http\Controllers\Publico;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Seguridad\AuditoriaTabla;
use App\Models\Proveedores\ProveedorTuristico;
use App\Models\Seguridad\Usuario;

/**
 * Registro público de viajeros (rol Cliente) y proveedores (rol Proveedor).
 * Crea la cuenta igual que Usuario::modificarOCrear (users.email = identificación para el login)
 * y, para proveedores, su ficha de proveedor turístico vinculada y pendiente de verificación.
 */
class RegistroController extends Controller
{
    private const ROLES = ['cliente' => 'Cliente', 'proveedor' => 'Proveedor'];

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'tipo' => 'required|in:cliente,proveedor',
                'nombre' => 'string|required|max:128',
                'identificacion_usuario' => 'string|required|max:128|unique:usuarios,identificacion_usuario|unique:users,email',
                'correo_electronico' => 'email|required|max:128|unique:usuarios,correo_electronico',
                'clave' => 'string|required|min:8|confirmed',
                'acepta_terminos' => 'accepted',
                'nombre_comercial' => 'required_if:tipo,proveedor|nullable|string|max:150',
                'nit' => 'nullable|string|max:50',
                'telefono' => 'nullable|string|max:30',
                'destino_id' => 'nullable|integer|exists:destinos,id',
            ], [
                'identificacion_usuario.unique' => 'El número de identificación ya está registrado.',
                'correo_electronico.unique' => 'El correo electrónico ya está registrado.',
                'clave.confirmed' => 'Las contraseñas no coinciden.',
                'acepta_terminos.accepted' => 'Debe aceptar los términos y la política de privacidad.',
                'nombre_comercial.required_if' => 'El nombre comercial es obligatorio para proveedores.',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $rolId = DB::table('roles')
                ->where('name', self::ROLES[$datos['tipo']])
                ->where('guard_name', 'api')
                ->value('id');
            if (!$rolId) {
                throw new Exception('El registro no está disponible: falta configurar el rol ' . self::ROLES[$datos['tipo']] . '.');
            }

            $auditoria = [
                'usuario_creacion_id' => 0,
                'usuario_creacion_nombre' => 'Registro público',
                'usuario_modificacion_id' => 0,
                'usuario_modificacion_nombre' => 'Registro público',
            ];

            $user = User::create([
                'name' => $datos['nombre'],
                'email' => $datos['identificacion_usuario'],
                'password' => Hash::make($datos['clave']),
            ]);

            // Sin sesión el guard por defecto es "web"; el rol es "api", por eso se asigna directo.
            DB::table('model_has_roles')->insert([
                'role_id' => $rolId,
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);

            $usuarioId = DB::table('usuarios')->insertGetId(array_merge($auditoria, [
                'user_id' => $user->id,
                'identificacion_usuario' => $datos['identificacion_usuario'],
                'nombre' => $datos['nombre'],
                'correo_electronico' => $datos['correo_electronico'],
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            AuditoriaTabla::crear([
                'externo' => true,
                'id_recurso' => $usuarioId,
                'nombre_recurso' => Usuario::class,
                'descripcion_recurso' => $datos['nombre'],
                'accion' => 'Crear',
                'recurso_original' => json_encode(['usuario_id' => $usuarioId, 'rol' => self::ROLES[$datos['tipo']]]),
            ]);

            if ($datos['tipo'] === 'proveedor') {
                $proveedorId = DB::table('proveedores_turisticos')->insertGetId(array_merge($auditoria, [
                    'usuario_id' => $usuarioId,
                    'nombre_comercial' => $datos['nombre_comercial'],
                    'nit' => $datos['nit'] ?? null,
                    'telefono' => $datos['telefono'] ?? null,
                    'correo' => $datos['correo_electronico'],
                    'destino_id' => $datos['destino_id'] ?? null,
                    'estado_verificacion' => 'pendiente',
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                AuditoriaTabla::crear([
                    'externo' => true,
                    'id_recurso' => $proveedorId,
                    'nombre_recurso' => ProveedorTuristico::class,
                    'descripcion_recurso' => $datos['nombre_comercial'],
                    'accion' => 'Crear',
                    'recurso_original' => json_encode(['proveedor_id' => $proveedorId, 'usuario_id' => $usuarioId]),
                ]);
            }

            DB::commit();
            $mensaje = $datos['tipo'] === 'proveedor'
                ? 'Tu cuenta de proveedor fue creada. Nuestro equipo verificará tu negocio.'
                : 'Tu cuenta fue creada. ¡Te damos la bienvenida!';

            return response(
                get_response_body([$mensaje, 2], ['identificacion_usuario' => $datos['identificacion_usuario']]),
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
