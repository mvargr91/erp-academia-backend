<?php

namespace App\Models\Experiencias;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Precio de una experiencia: es de adulto o de niño y tiene una o varias cantidades
 * (experiencia_precio_cantidades). Cada cantidad es el valor por persona que aplica
 * desde ese número de personas. Una experiencia puede tener varios precios de cada tipo.
 */
class ExperienciaPrecio extends Model
{
    use HasFactory;

    public const TIPOS = ['adulto', 'nino'];

    protected $table = 'experiencia_precios';

    protected $fillable = [
        'experiencia_id',
        'descripcion',
        'tipo',
        'estado',
    ];

    /** Reglas de un precio con sus cantidades; $prefijo permite validarlo anidado ("precios.*."). */
    public static function reglas($prefijo = '')
    {
        return [
            "{$prefijo}id" => 'integer|nullable',
            "{$prefijo}descripcion" => 'string|required|max:150',
            "{$prefijo}tipo" => 'required|in:' . implode(',', self::TIPOS),
            "{$prefijo}estado" => 'boolean',
            "{$prefijo}cantidades" => 'array|required|min:1',
            "{$prefijo}cantidades.*.cantidad" => 'integer|required|min:1',
            "{$prefijo}cantidades.*.valor" => 'numeric|required|min:0',
        ];
    }

    public static function mensajes($prefijo = '')
    {
        return [
            "{$prefijo}tipo.in" => 'El tipo de precio debe ser adulto o niño.',
            "{$prefijo}cantidades.required" => 'Cada precio necesita al menos una cantidad.',
            "{$prefijo}cantidades.min" => 'Cada precio necesita al menos una cantidad.',
        ];
    }

    /** Devuelve el mensaje de error si algún precio repite una cantidad, o null. */
    public static function validarCantidadesUnicas(array $precios)
    {
        foreach ($precios as $precio) {
            $cantidades = array_map(fn ($c) => (int) ($c['cantidad'] ?? 0), $precio['cantidades'] ?? []);
            if (count($cantidades) !== count(array_unique($cantidades))) {
                return 'El precio "' . ($precio['descripcion'] ?? '') . '" repite una cantidad de personas.';
            }
        }
        return null;
    }

    public static function obtenerColeccion($dto)
    {
        $precios = DB::table('experiencia_precios')
            ->select('id', 'experiencia_id', 'descripcion', 'tipo', 'estado')
            ->where('experiencia_id', $dto['experiencia_id'])
            ->when(!empty($dto['solo_activos']), fn ($q) => $q->where('estado', 1))
            ->orderByRaw("FIELD(tipo, 'adulto', 'nino')")
            ->orderBy('id')
            ->get();

        return self::conCantidades($precios);
    }

    /** Agrega a cada precio sus cantidades ordenadas y el valor mínimo. */
    public static function conCantidades($precios)
    {
        $cantidades = DB::table('experiencia_precio_cantidades')
            ->whereIn('experiencia_precio_id', $precios->pluck('id'))
            ->orderBy('cantidad')
            ->select('id', 'experiencia_precio_id', 'cantidad', 'valor')
            ->get()
            ->groupBy('experiencia_precio_id');

        return $precios->map(function ($precio) use ($cantidades) {
            $lista = ($cantidades[$precio->id] ?? collect())
                ->map(fn ($c) => ['id' => $c->id, 'cantidad' => $c->cantidad, 'valor' => $c->valor])
                ->values();
            $precio->cantidades = $lista;
            $precio->valor_minimo = $lista->min('valor');
            return $precio;
        })->values();
    }

    public static function cargar($id)
    {
        $precio = DB::table('experiencia_precios')
            ->where('id', $id)
            ->select('id', 'experiencia_id', 'descripcion', 'tipo', 'estado')
            ->get();

        return self::conCantidades($precio)->first();
    }

    public static function modificarOCrear($dto)
    {
        $precio = isset($dto['id']) ? ExperienciaPrecio::find($dto['id']) : new ExperienciaPrecio();
        $precio->fill($dto);
        if (!$precio->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el precio de la experiencia.');
        }

        self::sincronizarCantidades($precio->id, $dto['cantidades'] ?? []);
        self::actualizarPrecioDesde($precio->experiencia_id);

        return self::cargar($precio->id);
    }

    /**
     * Guarda los precios enviados desde el formulario de la experiencia:
     * crea o modifica los enviados y elimina los que ya no vienen.
     */
    public static function sincronizar($experienciaId, array $precios)
    {
        $conservados = [];
        foreach ($precios as $datos) {
            // Solo se modifica un id si pertenece a esta experiencia; si no, se crea uno nuevo.
            $existente = !empty($datos['id'])
                ? ExperienciaPrecio::where('experiencia_id', $experienciaId)->find($datos['id'])
                : null;

            $precio = $existente ?? new ExperienciaPrecio();
            $precio->fill([
                'experiencia_id' => $experienciaId,
                'descripcion' => $datos['descripcion'],
                'tipo' => $datos['tipo'],
                'estado' => $datos['estado'] ?? true,
            ]);
            if (!$precio->save()) {
                throw new Exception('Ocurrió un error al intentar guardar los precios de la experiencia.');
            }
            self::sincronizarCantidades($precio->id, $datos['cantidades'] ?? []);
            $conservados[] = $precio->id;
        }

        DB::table('experiencia_precios')
            ->where('experiencia_id', $experienciaId)
            ->whereNotIn('id', $conservados)
            ->delete();

        self::actualizarPrecioDesde($experienciaId);
    }

    /** Reemplaza las cantidades del precio por las enviadas. */
    private static function sincronizarCantidades($precioId, array $cantidades)
    {
        DB::table('experiencia_precio_cantidades')->where('experiencia_precio_id', $precioId)->delete();

        $filas = array_map(fn ($c) => [
            'experiencia_precio_id' => $precioId,
            'cantidad' => (int) $c['cantidad'],
            'valor' => $c['valor'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $cantidades);

        if ($filas) {
            DB::table('experiencia_precio_cantidades')->insert($filas);
        }
    }

    /**
     * El "precio desde" que se muestra en el portal y se usa en filtros y orden:
     * el menor valor de los precios de adulto activos (si no hay de adulto, de cualquier tipo).
     */
    public static function actualizarPrecioDesde($experienciaId)
    {
        $base = DB::table('experiencia_precio_cantidades')
            ->join('experiencia_precios', 'experiencia_precios.id', '=', 'experiencia_precio_cantidades.experiencia_precio_id')
            ->where('experiencia_precios.experiencia_id', $experienciaId)
            ->where('experiencia_precios.estado', 1);

        $minimo = (clone $base)->where('experiencia_precios.tipo', 'adulto')->min('experiencia_precio_cantidades.valor')
            ?? $base->min('experiencia_precio_cantidades.valor');

        DB::table('experiencias')->where('id', $experienciaId)->update(['precio_desde' => $minimo]);
    }

    public static function eliminar($id)
    {
        $precio = ExperienciaPrecio::find($id);
        $experienciaId = $precio->experiencia_id;
        $eliminado = $precio->delete();
        self::actualizarPrecioDesde($experienciaId);

        return $eliminado;
    }
}
