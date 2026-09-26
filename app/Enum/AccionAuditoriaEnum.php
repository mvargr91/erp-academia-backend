<?php namespace App\Enum;

use ReflectionClass;

class AccionAuditoriaEnum
{

    const CREAR = "Crear";
    const MODIFICAR = "Modificar";
    const ELIMINAR = "Eliminar";
    const CAMBIO_CONTRASENA = "Cambio Contraseña";

    public static function obtenerOpciones() {
        $oClass = new ReflectionClass(AccionAuditoriaEnum::class);
        $constants = $oClass->getConstants();
        return array_values($constants);
    }

}