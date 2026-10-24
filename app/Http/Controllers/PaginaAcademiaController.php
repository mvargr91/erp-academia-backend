<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Support\Academias\Apariencia;
use App\Support\Academias\GestorAcademias;

/**
 * index.html del frontend con la marca de la academia del subdominio: título, texto, logo e ícono.
 *
 * WhatsApp, Facebook y los buscadores no ejecutan JavaScript: leen las etiquetas del HTML tal como
 * llega. Por eso, en producción, el servidor web pide aquí la página (con el Host de la academia y la
 * URL original en la cabecera X-Pagina-Url) en lugar de servir el index.html estático, que solo trae
 * la marca del ERP. Las demás rutas del frontend (JS, CSS, imágenes) siguen siendo estáticas.
 */
class PaginaAcademiaController extends Controller
{
    public function __invoke(Request $request)
    {
        $archivo = config('academias.frontend_index');
        $html = $archivo && is_file($archivo) ? file_get_contents($archivo) : false;
        if ($html === false) {
            return response('No se encontró el index.html del frontend (FRONTEND_INDEX).', Response::HTTP_NOT_FOUND);
        }

        $academia = GestorAcademias::actual() ?? GestorAcademias::resolver($request);
        if ($academia) {
            $url = $request->header('X-Pagina-Url') ?: $request->fullUrl();
            $partes = parse_url($url);
            $origen = ($partes['scheme'] ?? 'https') . '://' . ($partes['host'] ?? $request->getHost())
                . (isset($partes['port']) ? ':' . $partes['port'] : '');
            $html = self::conMarca($html, Apariencia::paraPagina($academia, $origen, $url));
        }

        // Sin caché: al cambiar la apariencia, la próxima visita (y la próxima vista previa) ya sale con ella.
        return response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /** Quita del HTML el título, el ícono y las etiquetas para compartir del ERP y pone las de la academia. */
    private static function conMarca(string $html, array $p): string
    {
        $html = preg_replace([
            '#<title>.*?</title>\s*#si',
            '#<meta\b[^>]*\b(?:name|property)="(?:description|theme-color|og:[^"]*|twitter:[^"]*)"[^>]*>\s*#si',
            '#<link\b[^>]*\brel="(?:icon|apple-touch-icon)"[^>]*>\s*#si',
        ], '', $html);

        $e = fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
        $etiquetas = implode("\n    ", [
            "<title>{$e($p['titulo'])}</title>",
            "<meta name=\"description\" content=\"{$e($p['descripcion'])}\"/>",
            "<meta name=\"theme-color\" content=\"{$e($p['color'])}\"/>",
            "<link rel=\"icon\" href=\"{$e($p['icono'])}\"/>",
            "<link rel=\"apple-touch-icon\" href=\"{$e($p['icono'])}\"/>",
            '<meta property="og:type" content="website"/>',
            "<meta property=\"og:site_name\" content=\"{$e($p['titulo'])}\"/>",
            "<meta property=\"og:title\" content=\"{$e($p['titulo'])}\"/>",
            "<meta property=\"og:description\" content=\"{$e($p['descripcion'])}\"/>",
            "<meta property=\"og:image\" content=\"{$e($p['imagen'])}\"/>",
            "<meta property=\"og:url\" content=\"{$e($p['url'])}\"/>",
            '<meta name="twitter:card" content="summary"/>',
            "<meta name=\"twitter:title\" content=\"{$e($p['titulo'])}\"/>",
            "<meta name=\"twitter:description\" content=\"{$e($p['descripcion'])}\"/>",
            "<meta name=\"twitter:image\" content=\"{$e($p['imagen'])}\"/>",
        ]);

        return preg_replace('#<meta\s+charset=[^>]*>#i', "$0\n    {$etiquetas}", $html, 1);
    }
}
