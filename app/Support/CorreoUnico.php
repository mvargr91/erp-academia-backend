<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Control de aplicación: un correo (o documento) no se registra dos veces en la misma tabla
 * (alumnos, profesores).
 *
 * Al modificar solo se revisa si el dato cambió, para que los registros que ya estaban repetidos
 * antes de este control se puedan seguir editando sin tocarlo.
 */
class CorreoUnico
{
    private const QUIEN = ['alumnos' => 'el alumno', 'profesores' => 'el profesor'];

    private const DATO = ['correo' => 'El correo', 'documento' => 'El documento'];

    /** Agrega al validador el error de dato repetido en `$campo`, si lo hay. $columna: correo | documento. */
    public static function validar(Validator $validator, string $tabla, ?string $valor, ?int $id = null, string $campo = 'correo',
        string $columna = 'correo'): void
    {
        $validator->after(function (Validator $v) use ($tabla, $valor, $id, $campo, $columna) {
            if ($error = self::error($tabla, $valor, $id, $columna)) {
                $v->errors()->add($campo, $error);
            }
        });
    }

    /** Correo y documento de un alumno o profesor (formularios de alumnos y profesores). */
    public static function validarPersona(Validator $validator, string $tabla, array $datos, ?int $id = null, string $prefijo = ''): void
    {
        self::validar($validator, $tabla, $datos['correo'] ?? null, $id, "{$prefijo}correo");
        self::validar($validator, $tabla, $datos['documento'] ?? null, $id, "{$prefijo}documento", 'documento');
    }

    public static function error(string $tabla, ?string $valor, ?int $id = null, string $columna = 'correo'): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        if ($id && strcasecmp(trim((string) DB::table($tabla)->where('id', $id)->value($columna)), $valor) === 0) {
            return null;
        }
        $otro = DB::table($tabla)->where($columna, $valor)->when($id, fn ($q) => $q->where('id', '<>', $id))->first();
        return $otro
            ? self::DATO[$columna] . " {$valor} ya está registrado para " . self::QUIEN[$tabla] . " {$otro->nombres} {$otro->apellidos}."
            : null;
    }
}
