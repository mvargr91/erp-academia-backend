<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Services\Academia\EnviosCorreo;
use Illuminate\Support\Facades\Validator;
use App\Support\Academias\GestorAcademias;

/** Envíos de correo manuales/masivos y su seguimiento por destinatario. */
class EnvioCorreoController extends Controller
{
    private function formatear(object $e): array
    {
        $conteo = DB::table('envio_correo_destinatarios')->where('envio_id', $e->id)
            ->groupBy('estado')->select('estado', DB::raw('COUNT(*) as n'))->pluck('n', 'estado');
        return [
            'id' => $e->id,
            'asunto' => $e->asunto,
            'audiencia' => $e->audiencia,
            'audiencia_nombre' => EnviosCorreo::AUDIENCIAS[$e->audiencia] ?? $e->audiencia,
            'total' => (int) $e->total,
            'enviados' => (int) ($conteo['enviado'] ?? 0),
            'pendientes' => (int) ($conteo['pendiente'] ?? 0),
            'errores' => (int) ($conteo['error'] ?? 0),
            'estado' => $e->estado,
            'estado_nombre' => $e->estado === 'completado' ? 'Completado' : 'Enviando…',
            'usuario_creacion_nombre' => $e->usuario_creacion_nombre,
            'fecha_creacion' => Carbon::parse($e->created_at)->format('Y-m-d H:i:s'),
            'fecha_modificacion' => Carbon::parse($e->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    /** Audiencias disponibles: "Mis academias" solo en la academia administradora. */
    private function audienciasPermitidas(): array
    {
        $audiencias = EnviosCorreo::AUDIENCIAS;
        if (!GestorAcademias::actual()?->es_administradora) {
            unset($audiencias['academias']);
        }
        return $audiencias;
    }

    public function index(Request $request)
    {
        $pagina = DB::table('envios_correo')->orderBy('id', 'desc')->paginate((int) ($request->limite ?? 100));
        return response([
            'datos' => $pagina->getCollection()->map(fn ($e) => $this->formatear($e))->all(),
            'audiencias' => collect($this->audienciasPermitidas())->map(fn ($n, $id) => ['id' => $id, 'nombre' => $n])->values(),
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
        $envio = DB::table('envios_correo')->find($id);
        if (!$envio) {
            return response(get_response_body(['El envío no existe.']), Response::HTTP_NOT_FOUND);
        }
        $datos = $this->formatear($envio);
        $datos['texto'] = $envio->texto;
        $datos['destinatarios'] = DB::table('envio_correo_destinatarios')->where('envio_id', $id)->orderBy('nombre')
            ->get(['id', 'nombre', 'correo', 'estado', 'error', 'enviado_en']);
        return response($datos, Response::HTTP_OK);
    }

    private function reglas(): array
    {
        return [
            'audiencia' => 'required|in:' . implode(',', array_keys($this->audienciasPermitidas())),
            'curso_id' => 'required_if:audiencia,curso|nullable|integer|exists:cursos,id',
            'alumnos' => 'required_if:audiencia,seleccion|array',
            'alumnos.*' => 'integer',
        ];
    }

    private function filtro(Request $request): array
    {
        return array_filter(['curso_id' => $request->curso_id, 'alumnos' => $request->alumnos ?: null]);
    }

    /** Cuántos recibirán el correo, quiénes no tienen correo y si se supera el límite diario. */
    public function resumen(Request $request)
    {
        $validator = Validator::make($request->all(), $this->reglas(), [
            'curso_id.required_if' => 'Elige el curso.',
            'alumnos.required_if' => 'Elige al menos un alumno.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        return response(EnviosCorreo::resumen($request->audiencia, $this->filtro($request)), Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->reglas(), [
            'plantilla_id' => 'nullable|integer|exists:parametros_correos,id',
            'asunto' => 'required|string|max:150',
            'texto' => 'required|string',
        ]), [
            'curso_id.required_if' => 'Elige el curso.',
            'alumnos.required_if' => 'Elige al menos un alumno.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        try {
            $id = EnviosCorreo::enviar([
                'plantilla_id' => $request->plantilla_id,
                'asunto' => $request->asunto,
                'texto' => $request->texto,
                'audiencia' => $request->audiencia,
                'filtro' => $this->filtro($request),
            ], Auth::user()->usuario());
        } catch (Exception $e) {
            return response(get_response_body(['No se pudo crear el envío: ' . $e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $total = DB::table('envios_correo')->where('id', $id)->value('total');
        return response(get_response_body([
            $total ? "Envío creado: {$total} correos en cola. Se van enviando en el próximo minuto; revisa el avance en la lista."
                : 'Ningún destinatario tiene correo registrado: no se envió nada.', 2,
        ], ['id' => $id]), Response::HTTP_CREATED);
    }
}
