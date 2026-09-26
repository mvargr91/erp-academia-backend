<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Parametrizacion;
use App\Http\Controllers\Inversiones;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/descargar/{idCompania}/{nombreArchivo}', function ($idCompania, $nombreArchivo) {
    $filePath = "public/companias/{$idCompania}/{$nombreArchivo}";

    if (!Storage::exists($filePath)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    return Response::download(storage_path("app/{$filePath}"), $nombreArchivo);
});

Route::get('/descargarGestor/{idGestor}/{nombreArchivo}', function ($idGestor, $nombreArchivo) {
    $filePath = "public/gestores/{$idGestor}/{$nombreArchivo}"; 

    if (!Storage::exists($filePath)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    return Response::download(storage_path("app/{$filePath}"), $nombreArchivo);
});

Route::get('/descargarProveedor/{idProveedor}/{nombreArchivo}', function ($idProveedor, $nombreArchivo) {
    $filePath = "public/proveedores/{$idGestor}/{$nombreArchivo}"; 

    if (!Storage::exists($filePath)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    return Response::download(storage_path("app/{$filePath}"), $nombreArchivo);
});

Route::get('/descargarProyecto/{idProyecto}/{nombreArchivo}', function ($idProyecto, $nombreArchivo) {
    $filePath = "public/proyectos/{$idProyecto}/{$nombreArchivo}";

    if (!Storage::exists($filePath)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    return Response::download(storage_path("app/{$filePath}"), $nombreArchivo);
});

Route::get('/descargarFotoProyecto/{idProyecto}/{nombreArchivo}', function ($idProyecto, $nombreArchivo) {
    $filePath = "public/proyectos/{$idProyecto}/foto/{$nombreArchivo}";

    if (!Storage::exists($filePath)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    return Response::download(storage_path("app/{$filePath}"), $nombreArchivo);
});

Route::get('/verFotoProyecto/{idProyecto}/{nombreArchivo}', function ($idProyecto, $nombreArchivo) {
    // OJO: aquí ya NO pones "public/" porque usas el disco 'public'
    $relativePath = "proyectos/{$idProyecto}/foto/{$nombreArchivo}";

    // Verificas si existe en el disco 'public'
    if (!Storage::disk('public')->exists($relativePath)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    // Obtienes la ruta física
    $absolutePath = Storage::disk('public')->path($relativePath);

    // Retornas el archivo para verlo en el navegador (inline)
    return response()->file($absolutePath);
});

Route::get('/descargarInversionista/{idInversionista}/{nombreArchivo}', function ($idInversionista, $nombreArchivo) {
    $filePath = "public/inversionistas/{$idInversionista}/{$nombreArchivo}";

    if (!Storage::exists($filePath)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    return Response::download(storage_path("app/{$filePath}"), $nombreArchivo);
});

Route::get('/descargarActividades/{idProyecto}/{idEtapa}/{idActividad}/{nombreArchivo}', function ($idProyecto, $idEtapa, $idActividad, $nombreArchivo) {
    $filePath = "public/actividades_proyecto/P{$idProyecto}/E{$idEtapa}/A{$idActividad}/{$nombreArchivo}";

    if (!Storage::exists($filePath)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    return Response::download(storage_path("app/{$filePath}"), $nombreArchivo);
});

Route::group(["prefix" => "exporte-inversionistas"], function(){
    Route::get('/', [Parametrizacion\InversionistaController::class,'exportarInversionistas'])->name('inversionistas.exportarInversionistas');
});

Route::group(["prefix" => "exporte-proyectos"], function(){
    Route::get('/', [Parametrizacion\ProyectoController::class,'exportarProyectos'])->name('proyectos.exportarProyectos');
});

Route::get('/exportar-plan-detallado', [Inversiones\PlanDetalladoController::class, 'exportarPlanDetallado'])->name('programaciones.exportarPlanDetallado');

Route::get('/proyectos-simulaciones/{id}/pdf', [Parametrizacion\ProyectoSimulacionController::class, 'download'])->name('proyectos_simulaciones.download');

Route::get('proyectos-simulaciones/{id}/exportar-excel', [Parametrizacion\ProyectoSimulacionController::class, 'exportarExcel']);

