<?php

namespace App\Support;

/** Ayudas para perfilar las rutas (routes/api.php). */
class Permisos
{
    /**
     * Middleware de lectura de un catálogo: lo pasa quien pueda listar, crear o modificar en alguna
     * de las opciones que lo usan (su propia lista o los formularios de otras opciones que lo muestran).
     *
     * @param string[] $entidades  Entidades de los permisos: ['Alumno', 'Curso'] → ListarAlumno|CrearAlumno|…
     * @param string[] $otros      Permisos sueltos que también dan acceso (p. ej. PagarPaquete).
     */
    public static function lectura(array $entidades, array $otros = []): string
    {
        $permisos = $otros;
        foreach ($entidades as $entidad) {
            array_push($permisos, "Listar{$entidad}", "Crear{$entidad}", "Modificar{$entidad}");
        }
        return 'permission:' . implode('|', $permisos);
    }
}
