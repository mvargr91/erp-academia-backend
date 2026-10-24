<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Support\Academias\Apariencia;

Route::get('/', function () {
    return view('welcome');
});

// index.html del frontend con la marca de la academia del subdominio (vista previa al compartir el enlace).
// El servidor web lo pide en lugar de servir el index.html estático: ver PaginaAcademiaController.
Route::get('/pagina-academia', \App\Http\Controllers\PaginaAcademiaController::class)->name('pagina-academia');

// Logos e imagen de login de cada academia (el nombre del archivo cambia en cada subida: caché larga).
Route::get('/marca/{codigo}/{archivo}', function (string $codigo, string $archivo) {
    $ruta = Apariencia::carpeta($codigo) . '/' . $archivo;
    abort_unless(Storage::disk('public')->exists($ruta), 404);
    return response()->file(Storage::disk('public')->path($ruta), ['Cache-Control' => 'public, max-age=31536000, immutable']);
})->where(['codigo' => '[a-z0-9\-]+', 'archivo' => '[a-z0-9\-]+\.(png|jpg|jpeg|webp)']);
