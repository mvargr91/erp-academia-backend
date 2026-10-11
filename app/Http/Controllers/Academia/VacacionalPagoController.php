<?php

namespace App\Http\Controllers\Academia;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Academia\Sede;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Academia\Vacacionales;

/** Consulta de los pagos de vacacionales. Se registran y se quitan desde la inscripción. */
class VacacionalPagoController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('vacacional_pagos as p')
            ->join('vacacional_inscripciones as i', 'i.id', '=', 'p.inscripcion_id')
            ->join('vacacional_alumnos as a', 'a.id', '=', 'i.alumno_id')
            ->join('vacacional_cursos as c', 'c.id', '=', 'i.curso_id')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->select('p.*', 'c.nombre as curso_nombre', 's.nombre as sede_nombre', DB::raw("CONCAT(a.nombres,' ',a.apellidos) as alumno_nombre"));

        if ($request->filled('nombre')) {
            $query->where(DB::raw("CONCAT(a.nombres,' ',a.apellidos)"), 'like', '%' . $request->nombre . '%');
        }
        if ($request->filled('curso_id')) {
            $query->where('i.curso_id', $request->curso_id);
        }
        if ($request->filled('desde')) {
            $query->where('p.fecha_pago', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->where('p.fecha_pago', '<=', $request->hasta);
        }
        Sede::filtrar($query, 'c.sede_id')->orderBy('p.fecha_pago', 'desc')->orderBy('p.id', 'desc');

        return response(Vacacionales::paginar($query, $request, fn ($p) => array_merge([
            'id' => $p->id,
            'inscripcion_id' => $p->inscripcion_id,
            'alumno_nombre' => $p->alumno_nombre,
            'curso_nombre' => $p->curso_nombre,
            'sede_nombre' => $p->sede_nombre,
            'monto' => (float) $p->monto,
            'fecha_pago' => $p->fecha_pago,
            'metodo_pago' => $p->metodo_pago,
            'referencia' => $p->referencia,
        ], Vacacionales::auditoriaDe($p))), Response::HTTP_OK);
    }
}
