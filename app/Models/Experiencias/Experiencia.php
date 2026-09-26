<?php

namespace App\Models\Experiencias;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Experiencia extends Model
{
    use HasFactory;

    protected $table = 'experiencias';

    protected $fillable = [
        'proveedor_id',
        'destino_id',
        'nombre',
        'slug',
        'descripcion',
        'idioma',
        'incluye',
        'no_incluye',
        'precio_desde',
        'duracion',
        'capacidad_maxima',
        'punto_encuentro',
        'direccion',
        'latitud',
        'longitud',
        'estado',
        'destacada',
        'verificada',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('experiencias')
            ->select(
                'experiencias.id',
                'experiencias.nombre',
                'experiencias.estado',
            )
            ->where('experiencias.estado', 'publicada');

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('experiencias')
            ->join('destinos', 'destinos.id', '=', 'experiencias.destino_id')
            ->join('proveedores_turisticos', 'proveedores_turisticos.id', '=', 'experiencias.proveedor_id')
            ->select(
                'experiencias.id',
                'experiencias.proveedor_id',
                'proveedores_turisticos.nombre_comercial as proveedor_nombre',
                'experiencias.destino_id',
                'destinos.nombre as destino_nombre',
                'experiencias.nombre',
                'experiencias.slug',
                'experiencias.descripcion',
                'experiencias.idioma',
                'experiencias.incluye',
                'experiencias.no_incluye',
                'experiencias.precio_desde',
                'experiencias.duracion',
                'experiencias.capacidad_maxima',
                'experiencias.punto_encuentro',
                'experiencias.direccion',
                'experiencias.latitud',
                'experiencias.longitud',
                'experiencias.estado',
                'experiencias.destacada',
                'experiencias.verificada',
                'experiencias.usuario_creacion_id',
                'experiencias.usuario_creacion_nombre',
                'experiencias.usuario_modificacion_id',
                'experiencias.usuario_modificacion_nombre',
                'experiencias.created_at as fecha_creacion',
                'experiencias.updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where('experiencias.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        if (isset($dto['destino_id'])) {
            $query->where('experiencias.destino_id', $dto['destino_id']);
        }
        if (isset($dto['proveedor_id'])) {
            $query->where('experiencias.proveedor_id', $dto['proveedor_id']);
        }
        if (isset($dto['estado'])) {
            $query->where('experiencias.estado', $dto['estado']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'nombre') {
                    $query->orderBy('experiencias.nombre', $value);
                }
                if ($attribute == 'destino_nombre') {
                    $query->orderBy('destinos.nombre', $value);
                }
                if ($attribute == 'proveedor_nombre') {
                    $query->orderBy('proveedores_turisticos.nombre_comercial', $value);
                }
                if ($attribute == 'precio_desde') {
                    $query->orderBy('experiencias.precio_desde', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('experiencias.estado', $value);
                }
                if ($attribute == 'destacada') {
                    $query->orderBy('experiencias.destacada', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('experiencias.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('experiencias.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('experiencias.updated_at', 'desc');
        }

        $experiencias = $query->paginate($dto['limite'] ?? 100);
        $data = $experiencias->items();

        return [
            'datos' => $data,
            'desde' => $experiencias->firstItem(),
            'hasta' => $experiencias->lastItem(),
            'por_pagina' => $experiencias->perPage(),
            'pagina_actual' => $experiencias->currentPage(),
            'ultima_pagina' => $experiencias->lastPage(),
            'total' => $experiencias->total(),
        ];
    }

    public static function cargar($id)
    {
        $experiencia = Experiencia::find($id);

        $categorias = DB::table('experiencia_categoria')
            ->join('categorias', 'categorias.id', '=', 'experiencia_categoria.categoria_id')
            ->where('experiencia_categoria.experiencia_id', $id)
            ->select('categorias.id', 'categorias.nombre')
            ->get();

        $caracteristicas = DB::table('experiencia_caracteristica')
            ->join('caracteristicas', 'caracteristicas.id', '=', 'experiencia_caracteristica.caracteristica_id')
            ->where('experiencia_caracteristica.experiencia_id', $id)
            ->select('caracteristicas.id', 'caracteristicas.nombre', 'experiencia_caracteristica.incluida')
            ->get();

        return [
            'id' => $experiencia->id,
            'proveedor_id' => $experiencia->proveedor_id,
            'destino_id' => $experiencia->destino_id,
            'nombre' => $experiencia->nombre,
            'slug' => $experiencia->slug,
            'descripcion' => $experiencia->descripcion,
            'idioma' => $experiencia->idioma,
            'incluye' => $experiencia->incluye,
            'no_incluye' => $experiencia->no_incluye,
            'precio_desde' => $experiencia->precio_desde,
            'duracion' => $experiencia->duracion,
            'capacidad_maxima' => $experiencia->capacidad_maxima,
            'punto_encuentro' => $experiencia->punto_encuentro,
            'direccion' => $experiencia->direccion,
            'latitud' => $experiencia->latitud,
            'longitud' => $experiencia->longitud,
            'estado' => $experiencia->estado,
            'destacada' => $experiencia->destacada,
            'verificada' => $experiencia->verificada,
            'categorias' => $categorias,
            'caracteristicas' => $caracteristicas,
            'precios' => ExperienciaPrecio::obtenerColeccion(['experiencia_id' => $id]),
            'usuario_creacion_id' => $experiencia->usuario_creacion_id,
            'usuario_creacion_nombre' => $experiencia->usuario_creacion_nombre,
            'usuario_modificacion_id' => $experiencia->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $experiencia->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($experiencia->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($experiencia->updated_at))->format('Y-m-d H:i:s'),
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if (isset($usuario) || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        $experiencia = isset($dto['id']) ? Experiencia::find($dto['id']) : new Experiencia();

        $experienciaOriginal = $experiencia->toJson();

        $experiencia->fill($dto);
        $guardado = $experiencia->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la experiencia.');
        }

        $auditoriaDto = [
            'id_recurso' => $experiencia->id,
            'nombre_recurso' => Experiencia::class,
            'descripcion_recurso' => $experiencia->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $experienciaOriginal : $experiencia->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $experiencia->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Experiencia::cargar($experiencia->id);
    }

    public static function cambiarEstado($id, $estado)
    {
        $experiencia = Experiencia::find($id);
        $experienciaOriginal = $experiencia->toJson();

        $experiencia->estado = $estado;
        $guardado = $experiencia->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar cambiar el estado de la experiencia.');
        }

        $auditoriaDto = [
            'id_recurso' => $experiencia->id,
            'nombre_recurso' => Experiencia::class,
            'descripcion_recurso' => $experiencia->nombre,
            'accion' => AccionAuditoriaEnum::MODIFICAR,
            'recurso_original' => $experienciaOriginal,
            'recurso_resultante' => $experiencia->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Experiencia::cargar($experiencia->id);
    }

    public static function eliminar($id)
    {
        $experiencia = Experiencia::find($id);

        $auditoriaDto = [
            'id_recurso' => $experiencia->id,
            'nombre_recurso' => Experiencia::class,
            'descripcion_recurso' => $experiencia->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $experiencia->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $experiencia->delete();
    }

    // ---------------------- Portal público -------------------------- //

    /**
     * Subconsultas comunes a las tarjetas del portal: imagen principal,
     * calificación de reseñas aprobadas y mejor promoción vigente.
     */
    private static function columnasTarjetaPublica()
    {
        $hoy = Carbon::now()->toDateString();

        return [
            DB::raw("(SELECT em.ruta_archivo FROM experiencia_multimedia em
                WHERE em.experiencia_id = experiencias.id AND em.tipo = 'foto' AND em.estado = 1
                ORDER BY em.orden ASC, em.id ASC LIMIT 1) AS imagen"),
            DB::raw("(SELECT ROUND(AVG(r.calificacion), 1) FROM resenas r
                WHERE r.experiencia_id = experiencias.id AND r.estado = 'aprobada') AS calificacion_promedio"),
            DB::raw("(SELECT COUNT(*) FROM resenas r
                WHERE r.experiencia_id = experiencias.id AND r.estado = 'aprobada') AS total_resenas"),
            DB::raw("(SELECT p.id FROM promociones p
                JOIN promocion_experiencia pe ON pe.promocion_id = p.id
                WHERE pe.experiencia_id = experiencias.id AND p.estado = 1
                AND p.fecha_inicio <= '{$hoy}' AND p.fecha_fin >= '{$hoy}'
                ORDER BY p.valor_descuento DESC LIMIT 1) AS promocion_id"),
        ];
    }

    public static function obtenerColeccionPublica($dto)
    {
        $query = DB::table('experiencias')
            ->join('destinos', 'destinos.id', '=', 'experiencias.destino_id')
            ->join('proveedores_turisticos', 'proveedores_turisticos.id', '=', 'experiencias.proveedor_id')
            ->where('experiencias.estado', 'publicada')
            ->where('destinos.estado', 1)
            ->where('proveedores_turisticos.estado', 1)
            ->select(array_merge([
                'experiencias.id',
                'experiencias.nombre',
                'experiencias.slug',
                'experiencias.precio_desde',
                'experiencias.duracion',
                'experiencias.idioma',
                'experiencias.latitud',
                'experiencias.longitud',
                'experiencias.destacada',
                'experiencias.verificada',
                'destinos.nombre as destino_nombre',
                'destinos.slug as destino_slug',
                'proveedores_turisticos.nombre_comercial as proveedor_nombre',
                'proveedores_turisticos.estado_verificacion as proveedor_verificacion',
            ], self::columnasTarjetaPublica()));

        if (isset($dto['texto'])) {
            $query->where(function ($q) use ($dto) {
                $q->where('experiencias.nombre', 'like', '%' . $dto['texto'] . '%')
                    ->orWhere('destinos.nombre', 'like', '%' . $dto['texto'] . '%');
            });
        }
        if (isset($dto['destino'])) {
            $query->where('destinos.slug', $dto['destino']);
        }
        if (isset($dto['categoria'])) {
            $query->whereExists(function ($q) use ($dto) {
                $q->select(DB::raw(1))
                    ->from('experiencia_categoria')
                    ->join('categorias', 'categorias.id', '=', 'experiencia_categoria.categoria_id')
                    ->whereColumn('experiencia_categoria.experiencia_id', 'experiencias.id')
                    ->where('categorias.slug', $dto['categoria']);
            });
        }
        if (isset($dto['precio_min'])) {
            $query->where('experiencias.precio_desde', '>=', $dto['precio_min']);
        }
        if (isset($dto['precio_max'])) {
            $query->where('experiencias.precio_desde', '<=', $dto['precio_max']);
        }
        if (isset($dto['destacada'])) {
            $query->where('experiencias.destacada', 1);
        }
        if (isset($dto['promocion'])) {
            $hoy = Carbon::now()->toDateString();
            $query->whereExists(function ($q) use ($hoy) {
                $q->select(DB::raw(1))
                    ->from('promocion_experiencia')
                    ->join('promociones', 'promociones.id', '=', 'promocion_experiencia.promocion_id')
                    ->whereColumn('promocion_experiencia.experiencia_id', 'experiencias.id')
                    ->where('promociones.estado', 1)
                    ->where('promociones.fecha_inicio', '<=', $hoy)
                    ->where('promociones.fecha_fin', '>=', $hoy);
            });
        }

        switch ($dto['orden'] ?? 'relevancia') {
            case 'precio_asc':
                $query->orderBy('experiencias.precio_desde', 'asc');
                break;
            case 'precio_desc':
                $query->orderBy('experiencias.precio_desde', 'desc');
                break;
            case 'calificacion':
                $query->orderBy('calificacion_promedio', 'desc');
                break;
            case 'recientes':
                $query->orderBy('experiencias.created_at', 'desc');
                break;
            default:
                $query->orderBy('experiencias.destacada', 'desc')
                    ->orderBy('experiencias.verificada', 'desc')
                    ->orderBy('experiencias.updated_at', 'desc');
        }

        $experiencias = $query->paginate($dto['limite'] ?? 12);

        return [
            'datos' => $experiencias->items(),
            'desde' => $experiencias->firstItem(),
            'hasta' => $experiencias->lastItem(),
            'por_pagina' => $experiencias->perPage(),
            'pagina_actual' => $experiencias->currentPage(),
            'ultima_pagina' => $experiencias->lastPage(),
            'total' => $experiencias->total(),
        ];
    }

    public static function cargarPublica($slug)
    {
        $experiencia = DB::table('experiencias')
            ->join('destinos', 'destinos.id', '=', 'experiencias.destino_id')
            ->join('proveedores_turisticos', 'proveedores_turisticos.id', '=', 'experiencias.proveedor_id')
            ->where('experiencias.slug', $slug)
            ->where('experiencias.estado', 'publicada')
            ->select(array_merge([
                'experiencias.id',
                'experiencias.nombre',
                'experiencias.slug',
                'experiencias.proveedor_id',
                'experiencias.descripcion',
                'experiencias.idioma',
                'experiencias.incluye',
                'experiencias.no_incluye',
                'experiencias.precio_desde',
                'experiencias.duracion',
                'experiencias.capacidad_maxima',
                'experiencias.punto_encuentro',
                'experiencias.direccion',
                'experiencias.latitud',
                'experiencias.longitud',
                'experiencias.destacada',
                'experiencias.verificada',
                'destinos.nombre as destino_nombre',
                'destinos.slug as destino_slug',
                'proveedores_turisticos.nombre_comercial as proveedor_nombre',
                'proveedores_turisticos.estado_verificacion as proveedor_verificacion',
                'proveedores_turisticos.descripcion as proveedor_descripcion',
            ], self::columnasTarjetaPublica()))
            ->first();

        if (!$experiencia) {
            return null;
        }

        $id = $experiencia->id;
        $hoy = Carbon::now()->toDateString();

        $experiencia->multimedia = DB::table('experiencia_multimedia')
            ->where('experiencia_id', $id)->where('estado', 1)
            ->orderBy('orden')->orderBy('id')
            ->select('id', 'tipo', 'ruta_archivo', 'titulo', 'texto_alternativo')
            ->get();

        $experiencia->precios = ExperienciaPrecio::obtenerColeccion(['experiencia_id' => $id, 'solo_activos' => true]);

        $experiencia->horarios = DB::table('experiencia_horarios')
            ->where('experiencia_id', $id)->where('estado', 1)
            ->orderBy('dia_semana')->orderBy('hora_inicio')
            ->select('id', 'dia_semana', 'hora_inicio', 'hora_fin', 'capacidad')
            ->get();

        $experiencia->disponibilidad = DB::table('experiencia_disponibilidad')
            ->where('experiencia_id', $id)
            ->where('estado', 'disponible')
            ->where('cupos_disponibles', '>', 0)
            ->where('fecha', '>=', $hoy)
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->limit(60)
            ->select('id', 'fecha', 'hora_inicio', 'hora_fin', 'cupos_disponibles')
            ->get();

        $experiencia->categorias = DB::table('experiencia_categoria')
            ->join('categorias', 'categorias.id', '=', 'experiencia_categoria.categoria_id')
            ->where('experiencia_categoria.experiencia_id', $id)
            ->select('categorias.id', 'categorias.nombre', 'categorias.slug')
            ->get();

        $experiencia->caracteristicas = DB::table('experiencia_caracteristica')
            ->join('caracteristicas', 'caracteristicas.id', '=', 'experiencia_caracteristica.caracteristica_id')
            ->where('experiencia_caracteristica.experiencia_id', $id)
            ->select('caracteristicas.id', 'caracteristicas.nombre', 'caracteristicas.icono', 'experiencia_caracteristica.incluida')
            ->get();

        $experiencia->resenas = DB::table('resenas')
            ->leftJoin('usuarios', 'usuarios.id', '=', 'resenas.usuario_id')
            ->where('resenas.experiencia_id', $id)
            ->where('resenas.estado', 'aprobada')
            ->orderBy('resenas.created_at', 'desc')
            ->limit(20)
            ->select('resenas.id', 'resenas.calificacion', 'resenas.comentario', 'usuarios.nombre as usuario_nombre', 'resenas.created_at as fecha')
            ->get();

        $experiencia->promocion = $experiencia->promocion_id
            ? DB::table('promociones')
                ->where('id', $experiencia->promocion_id)
                ->select('id', 'nombre', 'tipo_descuento', 'valor_descuento', 'fecha_fin')
                ->first()
            : null;

        return $experiencia;
    }
}
