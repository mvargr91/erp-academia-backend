<?php

namespace App\Models\Proveedores;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentoProveedor extends Model
{
    use HasFactory;

    protected $table = 'documentos_proveedor';

    protected $fillable = [
        'proveedor_id',
        'tipo_documento',
        'nombre_archivo',
        'ruta_archivo',
        'estado',
        'fecha_vencimiento',
        'revisado_por',
        'revisado_en',
        'motivo_rechazo',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('documentos_proveedor')
            ->select(
                'documentos_proveedor.id',
                'documentos_proveedor.tipo_documento',
                'documentos_proveedor.estado',
            )
            ->where('documentos_proveedor.proveedor_id', $dto['proveedor_id']);

        $query->orderBy('tipo_documento', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('documentos_proveedor')
            ->select(
                'documentos_proveedor.id',
                'documentos_proveedor.proveedor_id',
                'documentos_proveedor.tipo_documento',
                'documentos_proveedor.nombre_archivo',
                'documentos_proveedor.ruta_archivo',
                'documentos_proveedor.estado',
                'documentos_proveedor.fecha_vencimiento',
                'documentos_proveedor.revisado_por',
                'documentos_proveedor.revisado_en',
                'documentos_proveedor.motivo_rechazo',
                'documentos_proveedor.usuario_creacion_id',
                'documentos_proveedor.usuario_creacion_nombre',
                'documentos_proveedor.usuario_modificacion_id',
                'documentos_proveedor.usuario_modificacion_nombre',
                'documentos_proveedor.created_at as fecha_creacion',
                'documentos_proveedor.updated_at as fecha_modificacion',
            );

        if (isset($dto['proveedor_id'])) {
            $query->where('documentos_proveedor.proveedor_id', $dto['proveedor_id']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'tipo_documento') {
                    $query->orderBy('documentos_proveedor.tipo_documento', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('documentos_proveedor.estado', $value);
                }
                if ($attribute == 'fecha_vencimiento') {
                    $query->orderBy('documentos_proveedor.fecha_vencimiento', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('documentos_proveedor.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('documentos_proveedor.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('documentos_proveedor.updated_at', 'desc');
        }

        $documentos = $query->paginate($dto['limite'] ?? 100);
        $data = $documentos->items();

        return [
            'datos' => $data,
            'desde' => $documentos->firstItem(),
            'hasta' => $documentos->lastItem(),
            'por_pagina' => $documentos->perPage(),
            'pagina_actual' => $documentos->currentPage(),
            'ultima_pagina' => $documentos->lastPage(),
            'total' => $documentos->total(),
        ];
    }

    public static function cargar($id)
    {
        $documento = DocumentoProveedor::find($id);

        return [
            'id' => $documento->id,
            'proveedor_id' => $documento->proveedor_id,
            'tipo_documento' => $documento->tipo_documento,
            'nombre_archivo' => $documento->nombre_archivo,
            'ruta_archivo' => $documento->ruta_archivo,
            'estado' => $documento->estado,
            'fecha_vencimiento' => $documento->fecha_vencimiento,
            'revisado_por' => $documento->revisado_por,
            'revisado_en' => $documento->revisado_en,
            'motivo_rechazo' => $documento->motivo_rechazo,
            'usuario_creacion_id' => $documento->usuario_creacion_id,
            'usuario_creacion_nombre' => $documento->usuario_creacion_nombre,
            'usuario_modificacion_id' => $documento->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $documento->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($documento->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($documento->updated_at))->format('Y-m-d H:i:s'),
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user ? $user->usuario() : null;

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if ($usuario || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        $documento = isset($dto['id']) ? DocumentoProveedor::find($dto['id']) : new DocumentoProveedor();

        $documentoOriginal = $documento->toJson();

        $documento->fill($dto);
        $guardado = $documento->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar el documento del proveedor.');
        }

        $auditoriaDto = [
            'id_recurso' => $documento->id,
            'nombre_recurso' => DocumentoProveedor::class,
            'descripcion_recurso' => $documento->nombre_archivo,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $documentoOriginal : $documento->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $documento->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return DocumentoProveedor::cargar($documento->id);
    }

    public static function eliminar($id)
    {
        $documento = DocumentoProveedor::find($id);

        if (Storage::exists($documento->ruta_archivo)) {
            Storage::delete($documento->ruta_archivo);
        }

        $auditoriaDto = [
            'id_recurso' => $documento->id,
            'nombre_recurso' => DocumentoProveedor::class,
            'descripcion_recurso' => $documento->nombre_archivo,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $documento->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $documento->delete();
    }
}
