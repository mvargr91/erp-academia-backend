<?php

namespace App\Http\Controllers\Academia;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Support\Academias\GestorAcademias;

/**
 * Imágenes dentro del contenido de los correos (envíos y plantillas): el editor las sube aquí y las
 * inserta con su URL pública, que es la que carga el correo del destinatario. Solo imágenes (PNG, JPG,
 * WEBP, GIF; el tipo se verifica por el contenido, no por el nombre): nada de SVG ni otros archivos.
 */
class ImagenCorreoController extends Controller
{
    public const FORMATOS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /** POST /v1/correo-imagenes (multipart: imagen) → { url } */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimes:' . implode(',', self::FORMATOS) . '|max:3072',
        ], [
            'imagen.required' => 'Elige una imagen.',
            'imagen.file' => 'Solo se permiten imágenes.',
            'imagen.mimes' => 'Solo se permiten imágenes PNG, JPG, WEBP o GIF.',
            'imagen.max' => 'La imagen no puede pesar más de 3 MB.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $academia = GestorAcademias::actual();
        $archivo = $request->file('imagen');
        $nombre = Str::lower(Str::random(16)) . '.' . $archivo->extension();
        $archivo->storeAs(self::carpeta($academia->codigo), $nombre, 'public');

        return response(['url' => self::url($academia->codigo, $nombre)], Response::HTTP_CREATED);
    }

    public static function carpeta(string $codigo): string
    {
        return 'correos/' . $codigo;
    }

    public static function url(string $codigo, string $archivo): string
    {
        return rtrim(config('app.url'), '/') . '/correo-imagen/' . $codigo . '/' . $archivo;
    }
}
