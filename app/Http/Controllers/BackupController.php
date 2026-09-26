<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BackupController extends Controller
{
    public function createBackup(Request $request)
    {
        try {
            // Ejecutar el comando de backup
            Artisan::call('backup:run');

            return response()->json([
                'status' => 'success',
                'message' => 'Copia de seguridad creada exitosamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el copia de seguridad: ' . $e->getMessage()
            ], 500);
        }
    }
}
