<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Support\Configuracion\Configuracion;

/**
 * Parámetros del sistema de la academia. Los códigos los define el sistema
 * (CatalogoConfiguracion); aquí solo se consultan y se edita su valor.
 */
class ParametroController extends Controller
{
    private const TIPOS = ['numero' => 'Número', 'porcentaje' => 'Porcentaje', 'texto' => 'Texto'];

    private function formatear(object $p): array
    {
        return [
            'id' => $p->id,
            'codigo' => $p->codigo_parametro,
            'descripcion' => $p->descripcion_parametro,
            'valor' => $p->valor_parametro,
            'tipo' => $p->tipo,
            'tipo_nombre' => self::TIPOS[$p->tipo] ?? $p->tipo,
            'grupo' => $p->grupo,
            'usuario_modificacion_nombre' => $p->usuario_modificacion_nombre,
            'fecha_modificacion' => Carbon::parse($p->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    public function index(Request $request)
    {
        $query = DB::table('parametros_constantes')->where('estado', 1);
        if ($request->filled('grupo')) {
            $query->where('grupo', $request->grupo);
        }
        if ($request->filled('nombre')) {
            $query->where(fn ($q) => $q->where('descripcion_parametro', 'like', "%{$request->nombre}%")
                ->orWhere('codigo_parametro', 'like', "%{$request->nombre}%"));
        }
        $pagina = $query->orderBy('orden')->orderBy('codigo_parametro')->paginate((int) ($request->limite ?? 100));
        return response([
            'datos' => $pagina->getCollection()->map(fn ($p) => $this->formatear($p))->all(),
            'grupos' => DB::table('parametros_constantes')->whereNotNull('grupo')->distinct()->orderBy('grupo')->pluck('grupo'),
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
        $parametro = DB::table('parametros_constantes')->find($id);
        if (!$parametro) {
            return response(get_response_body(['El parámetro no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->formatear($parametro), Response::HTTP_OK);
    }

    public function update(Request $request, $id)
    {
        $parametro = DB::table('parametros_constantes')->find($id);
        if (!$parametro) {
            return response(get_response_body(['El parámetro no existe.']), Response::HTTP_NOT_FOUND);
        }
        $regla = match ($parametro->tipo) {
            'numero' => 'required|numeric|min:0',
            'porcentaje' => 'required|numeric|between:0,100',
            default => 'nullable|string|max:2000',
        };
        $validator = Validator::make($request->all(), ['valor' => $regla], [
            'valor.between' => 'El porcentaje debe estar entre 0 y 100.',
            'valor.numeric' => 'Debe ser un número.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $usuario = Auth::user()->usuario();
        DB::table('parametros_constantes')->where('id', $id)->update([
            'valor_parametro' => (string) ($request->valor ?? ''),
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'updated_at' => Carbon::now(),
        ]);
        Configuracion::olvidarCache();
        return response(get_response_body(['El parámetro ha sido modificado.', 1],
            $this->formatear(DB::table('parametros_constantes')->find($id))), Response::HTTP_OK);
    }
}
