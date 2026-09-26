<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ExtractoController extends Controller
{
    public function enviarExtractos(Request $request)
    {
        try {
            // Ejecutar el comando extractos:enviar
            Artisan::call('extractos:enviar');

            return response()->json([
                'status' => 'success',
                'message' => 'Extractos enviados exitosamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al enviar los extractos: ' . $e->getMessage()
            ], 500);
        }
    }
}
