<?php

namespace App\Http\Controllers\Central;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Support\Calendario\FestivosColombia;

/**
 * Festivos nacionales (BD central, compartidos por todas las academias). Los de ley se
 * generan solos cada año; aquí el dueño del ERP agrega festivos extraordinarios o corrige.
 */
class FestivoController extends Controller
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    private function tabla()
    {
        return DB::connection('central')->table('festivos');
    }

    private function formatear(object $f): array
    {
        $fecha = Carbon::parse($f->fecha);
        return [
            'id' => $f->id,
            'fecha' => $f->fecha,
            'dia_semana' => self::DIAS[$fecha->dayOfWeek],
            'nombre' => $f->nombre,
            'origen' => $f->origen,
            'origen_nombre' => $f->origen === 'ley' ? 'Por ley' : 'Agregado a mano',
            'fecha_creacion' => Carbon::parse($f->created_at)->format('Y-m-d H:i:s'),
            'fecha_modificacion' => Carbon::parse($f->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    public function index(Request $request)
    {
        $anio = (int) ($request->anio ?: Carbon::today()->year);
        $query = $this->tabla()->whereYear('fecha', $anio)->orderBy('fecha');
        $pagina = $query->paginate((int) ($request->limite ?? 100));
        return response([
            'datos' => $pagina->getCollection()->map(fn ($f) => $this->formatear($f))->all(),
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
        $festivo = $this->tabla()->find($id);
        if (!$festivo) {
            return response(get_response_body(['El festivo no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->formatear($festivo), Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date|unique:central.festivos,fecha',
            'nombre' => 'required|string|max:120',
        ], ['fecha.unique' => 'Esa fecha ya está registrada como festivo.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $id = $this->tabla()->insertGetId([
            'fecha' => Carbon::parse($request->fecha)->toDateString(),
            'nombre' => $request->nombre,
            'origen' => 'manual',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        return response(get_response_body(['El festivo ha sido registrado para todas las academias.', 2],
            $this->formatear($this->tabla()->find($id))), Response::HTTP_CREATED);
    }

    public function destroy($id)
    {
        $this->tabla()->where('id', $id)->delete();
        return response(get_response_body(['El festivo ha sido eliminado.', 3]), Response::HTTP_OK);
    }

    /** Genera (o completa) los festivos de ley de un año. */
    public function generar(Request $request)
    {
        $anio = (int) ($request->anio ?: Carbon::today()->year + 1);
        $nuevos = 0;
        foreach (FestivosColombia::delAnio($anio) as $fecha => $nombre) {
            $nuevos += $this->tabla()->insertOrIgnore([
                'fecha' => $fecha, 'nombre' => $nombre, 'origen' => 'ley',
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
        }
        return response(get_response_body(["Festivos de {$anio}: {$nuevos} nuevos.", 2]), Response::HTTP_OK);
    }
}
