<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Support\Academias\GestorAcademias;
use App\Support\Configuracion\Configuracion;
use App\Services\Academia\EnviosCorreo;

/**
 * Plantillas de correo de la academia: asunto y cuerpo editables con variables {nombre}.
 * Los códigos los define el sistema; aquí se editan, se previsualizan, se prueban y se restauran.
 */
class PlantillaCorreoController extends Controller
{
    private function formatear(object $p): array
    {
        return [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'tipo' => $p->tipo,
            'tipo_nombre' => $p->tipo === 'manual' ? 'Manual' : 'Automática',
            'nombre' => $p->nombre,
            'asunto' => $p->asunto,
            'texto' => $p->texto,
            'variables' => $p->tipo === 'manual' ? EnviosCorreo::VARIABLES : (json_decode($p->variables ?? '{}', true) ?: (object) []),
            'estado' => $p->estado ? 1 : 0,
            'usuario_modificacion_nombre' => $p->usuario_modificacion_nombre,
            'fecha_modificacion' => Carbon::parse($p->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    public function index(Request $request)
    {
        $query = DB::table('parametros_correos')->where(fn ($q) => $q->whereNotNull('codigo')->orWhere('tipo', 'manual'));
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        // Selector del envío masivo: plantillas manuales activas.
        if ($request->ligera) {
            return response($query->where('tipo', 'manual')->where('estado', 1)->orderBy('nombre')->get()
                ->map(fn ($p) => $this->formatear($p))->values(), Response::HTTP_OK);
        }
        if ($request->filled('nombre')) {
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$request->nombre}%")->orWhere('asunto', 'like', "%{$request->nombre}%"));
        }
        $pagina = $query->orderByRaw("tipo = 'manual'")->orderBy('orden')->orderBy('nombre')->paginate((int) ($request->limite ?? 100));
        return response([
            'datos' => $pagina->getCollection()->map(fn ($p) => $this->formatear($p))->all(),
            'desde' => $pagina->firstItem(),
            'hasta' => $pagina->lastItem(),
            'por_pagina' => $pagina->perPage(),
            'pagina_actual' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $plantilla = DB::table('parametros_correos')->find($id);
        if (!$plantilla) {
            return response(get_response_body(['La plantilla no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->formatear($plantilla), Response::HTTP_OK);
    }

    /** Crea una plantilla manual (para envíos masivos). Las automáticas las define el sistema. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'asunto' => 'required|string|max:128',
            'texto' => 'required|string',
            'estado' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $usuario = Auth::user()->usuario();
        $id = DB::table('parametros_correos')->insertGetId([
            'codigo' => null,
            'tipo' => 'manual',
            'nombre' => $request->nombre,
            'asunto' => $request->asunto,
            'texto' => $request->texto,
            'estado' => $request->boolean('estado'),
            'orden' => 999,
            'usuario_creacion_id' => $usuario->id,
            'usuario_creacion_nombre' => $usuario->nombre,
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        return response(get_response_body(['La plantilla ha sido creada. Úsala en Envíos de correo.', 2],
            $this->formatear(DB::table('parametros_correos')->find($id))), Response::HTTP_CREATED);
    }

    public function destroy($id)
    {
        $plantilla = DB::table('parametros_correos')->find($id);
        if (!$plantilla) {
            return response(get_response_body(['La plantilla no existe.']), Response::HTTP_NOT_FOUND);
        }
        if ($plantilla->tipo !== 'manual') {
            return response(get_response_body(['Las plantillas automáticas no se eliminan: puedes desactivarlas o restaurarlas.']), Response::HTTP_CONFLICT);
        }
        DB::table('parametros_correos')->where('id', $id)->delete();
        return response(get_response_body(['La plantilla ha sido eliminada.', 3]), Response::HTTP_OK);
    }

    public function update(Request $request, $id)
    {
        $actual = DB::table('parametros_correos')->find($id);
        if (!$actual) {
            return response(get_response_body(['La plantilla no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'nombre' => $actual->tipo === 'manual' ? 'required|string|max:128' : 'nullable',
            'asunto' => 'required|string|max:128',
            'texto' => 'required|string',
            'estado' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $usuario = Auth::user()->usuario();
        DB::table('parametros_correos')->where('id', $id)->update([
            'nombre' => $actual->tipo === 'manual' ? $request->nombre : $actual->nombre,
            'asunto' => $request->asunto,
            'texto' => $request->texto,
            'estado' => $request->boolean('estado'),
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'updated_at' => Carbon::now(),
        ]);
        return response(get_response_body(['La plantilla ha sido modificada.', 1],
            $this->formatear(DB::table('parametros_correos')->find($id))), Response::HTTP_OK);
    }

    /** Valores de ejemplo para la vista previa / prueba: la descripción de cada variable entre corchetes. */
    private function ejemplo(object $plantilla): array
    {
        $variables = $plantilla->tipo === 'manual' ? EnviosCorreo::VARIABLES : (json_decode($plantilla->variables ?? '{}', true) ?: []);
        $ejemplo = [];
        foreach ($variables as $var => $descripcion) {
            $ejemplo[$var] = "[{$descripcion}]";
        }
        $ejemplo['academia'] = GestorAcademias::actual()?->nombre ?? 'Academia';
        return $ejemplo;
    }

    /** Arma la plantilla (con lo que se está editando) dentro del diseño real del correo. */
    private function htmlCompleto(object $plantilla, string $asunto, string $texto): array
    {
        $render = Configuracion::renderizar($asunto, $texto, $this->ejemplo($plantilla));
        $academia = GestorAcademias::actual();
        $esErp = str_starts_with((string) $plantilla->codigo, 'ERP_');
        if ($esErp) {
            $html = view('emails.facturacion.plantilla', [
                'cuerpo' => $render['html'], 'tipo' => 'factura',
                'datos' => ['marca' => config('academias.facturacion.marca'), 'academia' => '[Academia cliente]', 'numero' => 'CC-00000',
                    'datos_pago' => '', 'contacto' => ''],
            ])->render();
        } elseif ($plantilla->codigo === 'RECUPERAR_CLAVE') {
            $html = view('emails.reset-password', ['texto' => $render['html']])->render();
        } else {
            $html = view('emails.academia.plantilla', [
                'cuerpo' => $render['html'],
                'academia' => ['nombre' => $academia?->nombre ?? 'Academia', 'correo' => $academia?->correo, 'telefono' => $academia?->telefono],
                'datos' => ['alumno' => '[Nombre del alumno]'],
            ])->render();
        }
        return ['asunto' => $render['asunto'], 'html' => $html];
    }

    public function vistaPrevia(Request $request, $id)
    {
        $plantilla = DB::table('parametros_correos')->find($id);
        if (!$plantilla) {
            return response(get_response_body(['La plantilla no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->htmlCompleto($plantilla, $request->asunto ?? $plantilla->asunto, $request->texto ?? $plantilla->texto),
            Response::HTTP_OK);
    }

    /** Envía la plantilla (lo que se está editando) con datos de ejemplo al correo del usuario. */
    public function probar(Request $request, $id)
    {
        $plantilla = DB::table('parametros_correos')->find($id);
        if (!$plantilla) {
            return response(get_response_body(['La plantilla no existe.']), Response::HTTP_NOT_FOUND);
        }
        $destino = Auth::user()->usuario()->correo_electronico;
        if (!$destino) {
            return response(get_response_body(['Tu usuario no tiene correo registrado (Mi cuenta).']), Response::HTTP_BAD_REQUEST);
        }
        $correo = $this->htmlCompleto($plantilla, $request->asunto ?? $plantilla->asunto, $request->texto ?? $plantilla->texto);
        try {
            Mail::html($correo['html'], function ($m) use ($destino, $correo) {
                $m->to($destino)->subject('[PRUEBA] ' . $correo['asunto']);
            });
        } catch (Exception $e) {
            return response(get_response_body(['No se pudo enviar: ' . $e->getMessage()]), Response::HTTP_BAD_REQUEST);
        }
        return response(get_response_body(["Correo de prueba enviado a {$destino}.", 1]), Response::HTTP_OK);
    }

    /** Devuelve el texto por defecto (no guarda: el usuario decide si guardarlo). */
    public function porDefecto($id)
    {
        $plantilla = DB::table('parametros_correos')->find($id);
        $defecto = $plantilla ? Configuracion::porDefecto($plantilla->codigo) : null;
        if (!$defecto) {
            return response(get_response_body(['No hay texto por defecto para esta plantilla.']), Response::HTTP_NOT_FOUND);
        }
        return response($defecto, Response::HTTP_OK);
    }
}
