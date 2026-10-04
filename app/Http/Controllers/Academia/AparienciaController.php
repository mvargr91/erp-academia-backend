<?php

namespace App\Http\Controllers\Academia;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Support\Academias\Apariencia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Support\Academias\GestorAcademias;

/**
 * Apariencia del ERP de la academia activa (colores, logos, pantalla de ingreso, modo claro/oscuro).
 * `show` es pública: el frontend la pide antes del login para pintar la marca de la academia.
 */
class AparienciaController extends Controller
{
    public function show()
    {
        return response(Apariencia::de(GestorAcademias::actual()), Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $color = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $imagen = 'nullable|file|mimes:png,jpg,jpeg,webp';
        $validator = Validator::make($request->all(), [
            'color_primario' => $color,
            'color_menu' => $color,
            'color_acento' => $color,
            'modo' => 'nullable|in:' . implode(',', Apariencia::MODOS),
            'login_titulo' => 'nullable|string|max:80',
            'login_subtitulo' => 'nullable|string|max:160',
            'login_posicion' => 'nullable|in:' . implode(',', Apariencia::POSICIONES),
            'logo' => $imagen . '|max:2048',
            'logo_oscuro' => $imagen . '|max:2048',
            'login_fondo' => $imagen . '|max:5120',
        ], [
            'regex' => 'El color debe tener el formato #RRGGBB.',
            'mimes' => 'La imagen debe ser PNG, JPG o WEBP.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
            'logo_oscuro.max' => 'El logo para modo oscuro no puede pesar más de 2 MB.',
            'login_fondo.max' => 'La imagen de fondo no puede pesar más de 5 MB.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $academia = GestorAcademias::actual();
        $apariencia = $academia->apariencia ?? [];

        foreach (array_keys(Apariencia::DEFECTO) as $campo) {
            if (!$request->has($campo)) {
                continue;
            }
            $valor = $request->input($campo);
            // Vacío = volver al valor por defecto.
            if ($valor === null || $valor === '') {
                unset($apariencia[$campo]);
            } else {
                $apariencia[$campo] = str_starts_with($campo, 'color_') ? strtoupper($valor) : $valor;
            }
        }

        // Imágenes: archivo nuevo reemplaza; quitar_<campo>=1 vuelve a la imagen por defecto.
        $anteriores = [];
        foreach (array_keys(Apariencia::IMAGENES) as $campo) {
            if ($request->hasFile($campo)) {
                $anteriores[] = $apariencia[$campo] ?? null;
                $apariencia[$campo] = Apariencia::guardarImagen($academia, $campo, $request->file($campo));
            } elseif ($request->boolean('quitar_' . $campo)) {
                $anteriores[] = $apariencia[$campo] ?? null;
                unset($apariencia[$campo]);
            }
        }

        $academia->apariencia = $apariencia ?: null;
        $academia->save();
        foreach ($anteriores as $archivo) {
            Apariencia::borrarImagen($academia, $archivo);
        }

        return response(get_response_body(['La apariencia ha sido guardada.', 1], Apariencia::de($academia)), Response::HTTP_OK);
    }

    /** Vuelve a los colores, logos y textos por defecto del ERP. */
    public function restaurar()
    {
        $academia = GestorAcademias::actual();
        $academia->apariencia = null;
        $academia->save();
        Storage::disk('public')->deleteDirectory(Apariencia::carpeta($academia->codigo));

        return response(get_response_body(['Se restauró la apariencia por defecto.', 1], Apariencia::de($academia)), Response::HTTP_OK);
    }
}
