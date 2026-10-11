<?php

namespace App\Services\Academia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/** Lo que comparten las opciones de cursos vacacionales (alumnos, cursos, inscripciones y pagos). */
class Vacacionales
{
    public const METODOS_PAGO = ['efectivo', 'transferencia', 'tarjeta', 'otro'];

    /** Columnas de auditoría del registro que se guarda. */
    public static function auditoria(bool $nuevo): array
    {
        $usuario = Auth::user()->usuario();
        $ahora = Carbon::now();
        $datos = [
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'updated_at' => $ahora,
        ];
        if ($nuevo) {
            $datos += [
                'usuario_creacion_id' => $usuario->id,
                'usuario_creacion_nombre' => $usuario->nombre,
                'created_at' => $ahora,
            ];
        }
        return $datos;
    }

    /** Campos de auditoría que muestran las tablas. */
    public static function auditoriaDe(object $fila): array
    {
        return [
            'usuario_creacion_nombre' => $fila->usuario_creacion_nombre,
            'usuario_modificacion_nombre' => $fila->usuario_modificacion_nombre,
            'fecha_creacion' => Carbon::parse($fila->created_at)->format('Y-m-d H:i:s'),
            'fecha_modificacion' => Carbon::parse($fila->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    /** Respuesta paginada con el contrato de las listas del ERP. */
    public static function paginar($query, Request $request, callable $formato): array
    {
        $pagina = $query->paginate((int) ($request->limite ?? 100));
        return [
            'datos' => $pagina->getCollection()->map($formato)->values()->all(),
            'desde' => $pagina->firstItem(),
            'hasta' => $pagina->lastItem(),
            'por_pagina' => $pagina->perPage(),
            'pagina_actual' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ];
    }

    /** Fechas de clase de un curso, en orden. */
    public static function fechasCurso(int $cursoId): array
    {
        return DB::table('vacacional_curso_fechas')->where('curso_id', $cursoId)->orderBy('fecha')->pluck('fecha')->all();
    }

    /** Fechas como lista corta dd/mm/aaaa, para tablas, mensajes y correos. */
    public static function fechasTexto(array $fechas): string
    {
        return implode(', ', array_map(fn ($f) => Carbon::parse($f)->format('d/m/Y'), $fechas));
    }

    /** El saldo de la inscripción es su valor menos lo pagado. */
    public static function recalcularSaldo(int $inscripcionId): void
    {
        $valor = (float) DB::table('vacacional_inscripciones')->where('id', $inscripcionId)->value('valor');
        $pagado = (float) DB::table('vacacional_pagos')->where('inscripcion_id', $inscripcionId)->sum('monto');
        DB::table('vacacional_inscripciones')->where('id', $inscripcionId)->update(['saldo' => max(0, $valor - $pagado)]);
    }
}
