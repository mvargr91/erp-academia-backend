<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Control de aplicación: un correo no se registra dos veces en la misma tabla (alumnos, profesores).
 *
 * Al modificar solo se revisa si el correo cambió, para que los registros que ya estaban repetidos
 * antes de este control se puedan seguir editando sin tocar su correo.
 */
class CorreoUnico
{
    private const QUIEN = ['alumnos' => 'el alumno', 'profesores' => 'el profesor'];

    /** Agrega al validador el error de correo repetido en `$campo`, si lo hay. */
    public static function validar(Validator $validator, string $tabla, ?string $correo, ?int $id = null, string $campo = 'correo'): void
    {
        $validator->after(function (Validator $v) use ($tabla, $correo, $id, $campo) {
            if ($error = self::error($tabla, $correo, $id)) {
                $v->errors()->add($campo, $error);
            }
        });
    }

    public static function error(string $tabla, ?string $correo, ?int $id = null): ?string
    {
        $correo = trim((string) $correo);
        if ($correo === '') {
            return null;
        }
        if ($id && strcasecmp(trim((string) DB::table($tabla)->where('id', $id)->value('correo')), $correo) === 0) {
            return null;
        }
        $otro = DB::table($tabla)->where('correo', $correo)->when($id, fn ($q) => $q->where('id', '<>', $id))->first();
        return $otro
            ? "El correo {$correo} ya está registrado para " . self::QUIEN[$tabla] . " {$otro->nombres} {$otro->apellidos}."
            : null;
    }
}
