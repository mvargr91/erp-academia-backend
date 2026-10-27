<?php

namespace App\Http\Controllers\Academia;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Support\Academias\GestorAcademias;

/**
 * Datos de contacto de la academia activa (Configuración Academia → Datos de contacto): el correo y el
 * teléfono que van en el pie de todos sus correos; el correo es además a donde llegan las respuestas
 * de los alumnos. Viven en la BD central (academias), la misma ficha que edita Administración ERP.
 */
class DatosAcademiaController extends Controller
{
    /** GET /v1/datos-academia → { nombre, correo, telefono } */
    public function show()
    {
        return response($this->datos(), Response::HTTP_OK);
    }

    /** PUT /v1/datos-academia { correo, telefono } */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
        ], [
            'correo.email' => 'El correo de contacto no es válido.',
            'correo.max' => 'El correo de contacto no puede tener más de 150 caracteres.',
            'telefono.max' => 'El teléfono no puede tener más de 30 caracteres.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $academia = GestorAcademias::actual();
        $antes = ['correo' => $academia->correo, 'telefono' => $academia->telefono];
        $academia->correo = trim((string) $request->input('correo')) ?: null;
        $academia->telefono = trim((string) $request->input('telefono')) ?: null;
        $academia->save();
        Log::info("Datos de contacto de {$academia->codigo} cambiados por " . (Auth::user()?->usuario()?->nombre ?? '?'), [
            'antes' => $antes, 'ahora' => ['correo' => $academia->correo, 'telefono' => $academia->telefono],
        ]);

        return response(get_response_body(['Los datos de contacto han sido guardados.', 1], $this->datos()), Response::HTTP_OK);
    }

    private function datos(): array
    {
        $academia = GestorAcademias::actual();
        return ['nombre' => $academia->nombre, 'correo' => $academia->correo, 'telefono' => $academia->telefono];
    }
}
