<?php

namespace App\Models\Parametrizacion;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DescuentoTarifa extends Model
{
    use HasFactory;

    protected $table = 'descuentos_tarifas';

    protected $fillable = [
        'numero_anio',
        'numero_nivel',
        'id_nivel_consumo',
        'porcentaje_descuento',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerMatriz()
    {
        $numeroAnios = (int) DB::table('parametros_constantes')
            ->where('codigo_parametro', 'NUMERO_ANIOS_PERMANENCIA')
            ->value('valor_parametro');

        if ($numeroAnios <= 0) {
            $numeroAnios = 15;
        }

        $niveles = DB::table('niveles_consumo')
            ->select(
                'id',
                'numero_nivel',
                'limite_inferior',
                'limite_superior'
            )
            ->where('estado', 1)
            ->orderBy('numero_nivel', 'asc')
            ->get();

        $descuentos = DB::table('descuentos_tarifas')
            ->select(
                'id',
                'numero_anio',
                'numero_nivel',
                'id_nivel_consumo',
                'porcentaje_descuento'
            )
            ->get();

        $mapaDescuentos = [];
        foreach ($descuentos as $item) {
            $mapaDescuentos[$item->numero_anio][$item->numero_nivel] = [
                'id' => $item->id,
                'numero_anio' => $item->numero_anio,
                'numero_nivel' => $item->numero_nivel,
                'id_nivel_consumo' => $item->id_nivel_consumo,
                'porcentaje_descuento' => $item->porcentaje_descuento,
            ];
        }

        $columnas = [];
        foreach ($niveles as $nivel) {
            $columnas[] = [
                'id_nivel_consumo' => $nivel->id,
                'numero_nivel' => $nivel->numero_nivel,
                'titulo' => $nivel->limite_inferior . '-' . $nivel->limite_superior,
            ];
        }

        $filas = [];
        for ($anio = 1; $anio <= $numeroAnios; $anio++) {
            $valores = [];

            foreach ($niveles as $nivel) {
                $registro = $mapaDescuentos[$anio][$nivel->numero_nivel] ?? null;

                $valores[] = [
                    'id' => $registro['id'] ?? null,
                    'numero_anio' => $anio,
                    'numero_nivel' => $nivel->numero_nivel,
                    'id_nivel_consumo' => $nivel->id,
                    'porcentaje_descuento' => $registro['porcentaje_descuento'] ?? null,
                ];
            }

            $filas[] = [
                'numero_anio' => $anio,
                'valores' => $valores,
            ];
        }

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'numero_anios' => $numeroAnios,
        ];
    }

    public static function guardarMatriz(array $dto)
    {
        $user = Auth::user();
        $usuario = $user ? $user->usuario() : null;
        $jsonVacio = json_encode(new \stdClass());

        DB::beginTransaction();

        try {
            $matriz = $dto['matriz'] ?? [];

            foreach ($matriz as $fila) {
                $numeroAnio = (int) ($fila['numero_anio'] ?? 0);
                $valores = $fila['valores'] ?? [];

                foreach ($valores as $celda) {
                    $numeroNivel = (int) ($celda['numero_nivel'] ?? 0);
                    $idNivelConsumo = (int) ($celda['id_nivel_consumo'] ?? 0);
                    $porcentaje = $celda['porcentaje_descuento'] ?? null;

                    $registro = self::where('numero_anio', $numeroAnio)
                        ->where('id_nivel_consumo', $idNivelConsumo)
                        ->first();

                    // Si viene vacío, borrar si existe
                    if ($porcentaje === null || $porcentaje === '') {
                        if ($registro) {
                            $registroOriginal = $registro->toJson();
                            $idRegistro = $registro->id;
                            $descripcion = $numeroAnio . '-' . $numeroNivel;

                            $registro->delete();

                            AuditoriaTabla::crear([
                                'id_recurso' => $idRegistro,
                                'nombre_recurso' => self::class,
                                'descripcion_recurso' => $descripcion,
                                'accion' => AccionAuditoriaEnum::ELIMINAR,
                                'recurso_original' => $registroOriginal,
                                'recurso_resultante' => $jsonVacio,
                            ]);
                        }

                        continue;
                    }

                    // Normalizar para comparar
                    $porcentajeNormalizado = is_numeric($porcentaje) ? number_format((float) $porcentaje, 2, '.', '') : (string) $porcentaje;

                    // Si no existe, crear
                    if (!$registro) {
                        $registro = new self();
                        $registro->numero_anio = $numeroAnio;
                        $registro->numero_nivel = $numeroNivel;
                        $registro->id_nivel_consumo = $idNivelConsumo;
                        $registro->porcentaje_descuento = $porcentaje;
                        $registro->estado = 1;
                        $registro->usuario_creacion_id = $usuario->id ?? null;
                        $registro->usuario_creacion_nombre = $usuario->nombre ?? null;
                        $registro->usuario_modificacion_id = $usuario->id ?? null;
                        $registro->usuario_modificacion_nombre = $usuario->nombre ?? null;
                        $registro->save();

                        $registroActualizado = self::find($registro->id);

                        AuditoriaTabla::crear([
                            'id_recurso' => $registro->id,
                            'nombre_recurso' => self::class,
                            'descripcion_recurso' => $registro->numero_anio . '-' . $registro->numero_nivel,
                            'accion' => AccionAuditoriaEnum::CREAR,
                            'recurso_original' => $registroActualizado ? $registroActualizado->toJson() : $jsonVacio,
                            'recurso_resultante' => $jsonVacio,
                        ]);

                        continue;
                    }

                    // Comparar si realmente cambió
                    $porcentajeActual = is_numeric($registro->porcentaje_descuento)
                        ? number_format((float) $registro->porcentaje_descuento, 2, '.', '')
                        : (string) $registro->porcentaje_descuento;

                    $sinCambios =
                        (int) $registro->numero_anio === $numeroAnio &&
                        (int) $registro->numero_nivel === $numeroNivel &&
                        (int) $registro->id_nivel_consumo === $idNivelConsumo &&
                        $porcentajeActual === $porcentajeNormalizado &&
                        (int) $registro->estado === 1;

                    if ($sinCambios) {
                        continue;
                    }

                    // Si existe y cambió, actualizar
                    $recursoOriginal = $registro->toJson();

                    $registro->numero_anio = $numeroAnio;
                    $registro->numero_nivel = $numeroNivel;
                    $registro->id_nivel_consumo = $idNivelConsumo;
                    $registro->porcentaje_descuento = $porcentaje;
                    $registro->estado = 1;
                    $registro->usuario_modificacion_id = $usuario->id ?? null;
                    $registro->usuario_modificacion_nombre = $usuario->nombre ?? null;
                    $registro->save();

                    $registroActualizado = self::find($registro->id);

                    AuditoriaTabla::crear([
                        'id_recurso' => $registro->id,
                        'nombre_recurso' => self::class,
                        'descripcion_recurso' => $registro->numero_anio . '-' . $registro->numero_nivel,
                        'accion' => AccionAuditoriaEnum::MODIFICAR,
                        'recurso_original' => $recursoOriginal,
                        'recurso_resultante' => $registroActualizado ? $registroActualizado->toJson() : $jsonVacio,
                    ]);
                }
            }

            DB::commit();

            return self::obtenerMatriz();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


}
