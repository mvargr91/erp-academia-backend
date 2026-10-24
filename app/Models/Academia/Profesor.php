<?php

namespace App\Models\Academia;

use Exception;
use Carbon\Carbon;
use DomainException;
use App\Models\User;
use App\Enum\AccionAuditoriaEnum;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Profesor extends Model
{
    use HasFactory;

    protected $table = 'profesores';

    protected $fillable = [
        'usuario_id',
        'nombres',
        'apellidos',
        'documento',
        'telefono',
        'correo',
        'especialidad',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    /** Quien tiene este permiso ve todos los cursos aunque su usuario sea de un profesor. */
    public const VER_TODOS_LOS_CURSOS = 'ListarCurso';

    /** Días hacia atrás en que un profesor puede tomar o corregir la asistencia de sus cursos. */
    public const DIAS_CORRECCION_ASISTENCIA = 7;

    /**
     * Profesor del usuario conectado cuando solo puede ver sus cursos: su usuario está enlazado a un
     * profesor y no tiene el permiso de ver todos los cursos. null = ve todos (como siempre).
     */
    public static function soloSusCursos(): ?int
    {
        $user = Auth::user();
        if (!$user || $user->checkPermissionTo(self::VER_TODOS_LOS_CURSOS, 'api')) {
            return null;
        }
        $usuario = $user->usuario();
        $id = $usuario ? DB::table('profesores')->where('usuario_id', $usuario->id)->value('id') : null;
        return $id ? (int) $id : null;
    }

    /** ¿El curso es de otro profesor para el usuario conectado (que solo ve los suyos)? */
    public static function cursoAjeno(int $cursoId): bool
    {
        $profesorId = self::soloSusCursos();
        return $profesorId !== null
            && (int) DB::table('cursos')->where('id', $cursoId)->value('profesor_id') !== $profesorId;
    }

    /** Rol que reciben las cuentas creadas con «Dar acceso al ERP» (lo siembra AcademiaMenuSeeder). */
    public const ROL = 'Profesor';

    /**
     * Da acceso al ERP al profesor: su cuenta entra con su correo y la contraseña inicial que define el
     * administrador, que deberá cambiar al entrar. Si ya hay una cuenta con ese correo:
     *  - activa (p. ej. un administrador que también dicta clases): solo se enlaza, sin tocar su clave ni su rol;
     *  - desactivada (se le quitó el acceso antes): se reactiva con la contraseña nueva.
     * Devuelve el mensaje para el administrador.
     */
    public static function darAcceso(int $id, string $clave): string
    {
        $profesor = DB::table('profesores')->find($id);
        $correo = trim((string) $profesor->correo);
        if ($correo === '') {
            throw new DomainException('El profesor necesita un correo: es con lo que va a entrar al ERP.');
        }
        $rolId = DB::table('roles')->where('name', self::ROL)->where('guard_name', 'api')->value('id');
        if (!$rolId) {
            throw new DomainException('No existe el rol Profesor. Créalo en Seguridad → Roles.');
        }

        $existente = DB::table('usuarios')->where('identificacion_usuario', $correo)->orWhere('correo_electronico', $correo)->first();
        if ($existente) {
            $otro = DB::table('profesores')->where('usuario_id', $existente->id)->where('id', '!=', $id)->exists();
            if ($otro) {
                throw new DomainException("La cuenta {$existente->identificacion_usuario} ya es de otro profesor.");
            }
            DB::table('profesores')->where('id', $id)->update(['usuario_id' => $existente->id, 'updated_at' => Carbon::now()]);
            if ($existente->estado) {
                return "Se enlazó la cuenta que ya existía ({$existente->identificacion_usuario}): entra con su contraseña de siempre.";
            }
            DB::table('users')->where('id', $existente->user_id)->update(['password' => Hash::make($clave)]);
            DB::table('usuarios')->where('id', $existente->id)->update(['estado' => 1, 'debe_cambiar_clave' => true, 'updated_at' => Carbon::now()]);
            return "Se reactivó el acceso. Entra con {$existente->identificacion_usuario} y la contraseña que definiste; al entrar deberá cambiarla.";
        }

        Usuario::modificarOCrear([
            'nombre' => trim("{$profesor->nombres} {$profesor->apellidos}"),
            'correo_electronico' => $correo,
            'identificacion_usuario' => $correo,
            'rol_id' => $rolId,
            'clave' => $clave,
            'estado' => 1,
        ]);
        $usuarioId = DB::table('usuarios')->where('identificacion_usuario', $correo)->value('id');
        DB::table('usuarios')->where('id', $usuarioId)->update(['debe_cambiar_clave' => true]);
        DB::table('profesores')->where('id', $id)->update(['usuario_id' => $usuarioId, 'updated_at' => Carbon::now()]);
        return "Listo. Entra con {$correo} y la contraseña que definiste; al entrar deberá cambiarla.";
    }

    /**
     * Quita el acceso: desenlaza la cuenta y, si es una cuenta de profesor (rol Profesor), la desactiva y
     * cierra sus sesiones. Una cuenta con otro rol (un administrador que dicta clases) solo se desenlaza.
     */
    public static function quitarAcceso(int $id): string
    {
        $usuario = DB::table('usuarios')->where('id', DB::table('profesores')->where('id', $id)->value('usuario_id'))->first();
        DB::table('profesores')->where('id', $id)->update(['usuario_id' => null, 'updated_at' => Carbon::now()]);
        if (!$usuario) {
            return 'El profesor no tenía acceso al ERP.';
        }
        $user = User::find($usuario->user_id);
        if (!$user || !$user->hasRole(self::ROL, 'api') || $user->getRoleNames()->count() > 1) {
            return "Se desenlazó la cuenta {$usuario->identificacion_usuario}; como no es solo de profesor, sigue activa.";
        }
        DB::table('usuarios')->where('id', $usuario->id)->update(['estado' => 0, 'updated_at' => Carbon::now()]);
        foreach ($user->tokens as $token) {
            $token->revoke();
        }
        return "Se quitó el acceso: la cuenta {$usuario->identificacion_usuario} quedó desactivada.";
    }

    /** Cuenta con la que el profesor entra al ERP, o null. */
    private static function acceso(?int $usuarioId): ?array
    {
        $u = $usuarioId ? DB::table('usuarios')->find($usuarioId) : null;
        return $u ? [
            'identificacion_usuario' => $u->identificacion_usuario,
            'activo' => (bool) $u->estado,
            'debe_cambiar_clave' => (bool) $u->debe_cambiar_clave,
        ] : null;
    }

    public static function obtenerColeccionLigera($dto)
    {
        return DB::table('profesores')
            ->select('id', DB::raw("CONCAT(nombres,' ',apellidos) as nombre"))
            ->where('estado', 1)
            ->orderBy('nombres', 'asc')
            ->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('profesores')
            ->select(
                'id',
                'usuario_id',
                'nombres',
                'apellidos',
                'documento',
                'telefono',
                'correo',
                'especialidad',
                'estado',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at as fecha_creacion',
                'updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where(function ($q) use ($dto) {
                $q->where('nombres', 'like', "%{$dto['nombre']}%")
                    ->orWhere('apellidos', 'like', "%{$dto['nombre']}%");
            });
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if (in_array($attribute, ['nombres', 'apellidos', 'documento', 'correo', 'especialidad', 'estado', 'usuario_creacion_nombre'])) {
                    $query->orderBy($attribute, $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('updated_at', $value);
                }
            }
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $profesores = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $profesores->items(),
            'desde' => $profesores->firstItem(),
            'hasta' => $profesores->lastItem(),
            'por_pagina' => $profesores->perPage(),
            'pagina_actual' => $profesores->currentPage(),
            'ultima_pagina' => $profesores->lastPage(),
            'total' => $profesores->total(),
        ];
    }

    public static function cargar($id)
    {
        $profesor = Profesor::find($id);

        return [
            'id' => $profesor->id,
            'usuario_id' => $profesor->usuario_id,
            'nombres' => $profesor->nombres,
            'apellidos' => $profesor->apellidos,
            'documento' => $profesor->documento,
            'telefono' => $profesor->telefono,
            'correo' => $profesor->correo,
            'especialidad' => $profesor->especialidad,
            'estado' => $profesor->estado,
            'acceso' => self::acceso($profesor->usuario_id),
            'usuario_creacion_id' => $profesor->usuario_creacion_id,
            'usuario_creacion_nombre' => $profesor->usuario_creacion_nombre,
            'usuario_modificacion_id' => $profesor->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $profesor->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($profesor->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($profesor->updated_at))->format('Y-m-d H:i:s'),
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? null;
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? null;
        }
        $dto['usuario_modificacion_id'] = $usuario->id ?? null;
        $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? null;

        $profesor = isset($dto['id']) ? Profesor::find($dto['id']) : new Profesor();
        $original = $profesor->toJson();

        $profesor->fill($dto);
        if (!$profesor->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el profesor.');
        }

        AuditoriaTabla::crear([
            'id_recurso' => $profesor->id,
            'nombre_recurso' => Profesor::class,
            'descripcion_recurso' => "$profesor->nombres $profesor->apellidos",
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $profesor->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $profesor->toJson() : null,
        ]);

        return Profesor::cargar($profesor->id);
    }

    public static function eliminar($id)
    {
        $profesor = Profesor::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $profesor->id,
            'nombre_recurso' => Profesor::class,
            'descripcion_recurso' => "$profesor->nombres $profesor->apellidos",
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $profesor->toJson(),
        ]);

        return $profesor->delete();
    }
}
