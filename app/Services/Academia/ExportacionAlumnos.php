<?php

namespace App\Services\Academia;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel para que el dueño de la academia valide sus datos: alumnos por curso con lo que debe,
 * resumen por alumno, paquetes, vacacionales y alumnos sin curso. Solo lee; no cambia nada.
 * Cada hoja trae las columnas "¿Correcto?" y "Corrección" vacías para que las llene.
 * Trabaja sobre la academia activa (BD actual). Lo usan el comando academia:exportar-alumnos
 * y la descarga de la pantalla Alumnos.
 */
class ExportacionAlumnos
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    private const VALIDACION = ['¿Correcto? (Sí/No)', 'Corrección'];

    /** Guarda el Excel en $archivo y devuelve un resumen (conteos y deudas). */
    public function generar(string $archivo, string $titulo = 'Alumnos'): array
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setTitle($titulo);
        $libro->removeSheetByIndex(0);

        $cursos = $this->alumnosPorCurso();
        $paquetes = $this->paquetes();
        $vacacionales = $this->vacacionales();

        $this->hojaPorCurso($libro, $cursos);
        $this->hojaResumen($libro, $cursos, $paquetes, $vacacionales);
        $this->hojaPaquetes($libro, $paquetes);
        $this->hojaVacacionales($libro, $vacacionales);
        $this->hojaSinCurso($libro);
        $libro->setActiveSheetIndex(0);

        (new Xlsx($libro))->save($archivo);

        return [
            'matriculas' => $cursos->count(),
            'alumnos_con_curso' => $cursos->pluck('alumno_id')->unique()->count(),
            'deuda_cursos' => $cursos->where('saldo', '>', 0)->sum('saldo'),
            'deuda_paquetes' => $paquetes->where('saldo', '>', 0)->sum('saldo'),
            'deuda_vacacionales' => $vacacionales->where('saldo', '>', 0)->sum('saldo'),
        ];
    }

    private function alumnosPorCurso()
    {
        return DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->join('alumnos as a', 'a.id', '=', 'ca.alumno_id')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->leftJoin('profesores as p', 'p.id', '=', 'c.profesor_id')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->leftJoin('modalidades as m', 'm.id', '=', 'c.modalidad_id')
            ->leftJoin('alumnos as pa', 'pa.id', '=', 'ca.pareja_alumno_id')
            ->where('ca.estado', 1)
            ->select(
                'a.id as alumno_id', 'c.id as curso_id',
                DB::raw('COALESCE(c.nombre, r.nombre) as curso'), 'r.nombre as ritmo', 'c.dia', 'c.hora',
                DB::raw("TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,''))) as profesor"),
                's.nombre as sede', 'm.nombre as modalidad',
                DB::raw('(c.estado = 1 AND c.activo = 1) as curso_activo'),
                DB::raw("TRIM(CONCAT(COALESCE(a.nombres,''),' ',COALESCE(a.apellidos,''))) as alumno"),
                'a.documento', 'a.telefono', 'a.correo', 'a.estado as alumno_estado',
                DB::raw("TRIM(CONCAT(COALESCE(pa.nombres,''),' ',COALESCE(pa.apellidos,''))) as pareja"),
                'ca.fecha_matricula', 'ca.ultima_fecha_pago', 'ca.valor_especial', 'ca.valor_especial_motivo', 'ca.saldo',
                DB::raw('(SELECT COALESCE(SUM(pg.monto), 0) FROM pagos pg WHERE pg.alumno_id = ca.alumno_id AND pg.curso_id = ca.curso_id AND pg.paquete_id IS NULL) as pagado'),
            )
            ->orderBy('c.dia')->orderBy('c.hora')->orderBy('curso')->orderBy('a.nombres')->orderBy('a.apellidos')
            ->get()
            ->map(function ($f) {
                $f->saldo = (float) $f->saldo;
                $f->pagado = (float) $f->pagado;
                return $f;
            });
    }

    private function paquetes()
    {
        return DB::table('paquetes_alumno as pq')
            ->join('alumnos as a', 'a.id', '=', 'pq.alumno_id')
            ->leftJoin('planes as pl', 'pl.id', '=', 'pq.plan_id')
            ->where('pq.estado', 'activo')
            ->select(
                'a.id as alumno_id',
                DB::raw("TRIM(CONCAT(COALESCE(a.nombres,''),' ',COALESCE(a.apellidos,''))) as alumno"),
                'a.documento', 'a.telefono', 'a.correo',
                DB::raw("COALESCE(pl.nombre, 'Paquete') as plan"),
                'pq.fecha_compra', 'pq.fecha_vencimiento', 'pq.clases_total',
                DB::raw('(SELECT COUNT(*) FROM consumos_paquete cp WHERE cp.paquete_id = pq.id) as clases_usadas'),
                'pq.valor', 'pq.saldo',
            )
            ->orderBy('a.nombres')->orderBy('pq.fecha_compra')
            ->get()
            ->map(function ($f) {
                $f->saldo = (float) $f->saldo;
                $f->valor = (float) $f->valor;
                return $f;
            });
    }

    private function vacacionales()
    {
        return DB::table('vacacional_inscripciones as vi')
            ->join('vacacional_cursos as vc', 'vc.id', '=', 'vi.curso_id')
            ->join('alumnos as a', 'a.id', '=', 'vi.alumno_id')
            ->where('vi.estado', 1)
            ->select(
                'a.id as alumno_id', 'vc.nombre as curso',
                DB::raw("TRIM(CONCAT(COALESCE(a.nombres,''),' ',COALESCE(a.apellidos,''))) as alumno"),
                'a.documento', 'a.telefono', 'a.correo',
                'vi.fecha_inscripcion', 'vi.valor', 'vi.saldo',
            )
            ->orderBy('vc.nombre')->orderBy('a.nombres')
            ->get()
            ->map(function ($f) {
                $f->saldo = (float) $f->saldo;
                $f->valor = (float) $f->valor;
                return $f;
            });
    }

    private function hojaPorCurso(Spreadsheet $libro, $cursos): void
    {
        $filas = $cursos->map(fn ($f) => [
            $f->curso, $f->ritmo, self::DIAS[$f->dia] ?? $f->dia, substr((string) $f->hora, 0, 5), $f->profesor, $f->sede,
            $f->modalidad, $f->curso_activo ? 'Sí' : 'No',
            $f->alumno, $f->documento, $f->telefono, $f->correo, $f->pareja ?: null,
            $f->fecha_matricula, $f->ultima_fecha_pago,
            $f->valor_especial !== null ? (float) $f->valor_especial : null, $f->valor_especial_motivo,
            $f->pagado, $f->saldo > 0 ? $f->saldo : 0, $f->saldo < 0 ? -$f->saldo : 0,
        ]);
        $this->hoja($libro, 'Alumnos por curso', [
            'Curso', 'Ritmo', 'Día', 'Hora', 'Profesor', 'Sede', 'Modalidad', 'Curso activo',
            'Alumno', 'Documento', 'Teléfono', 'Correo', 'Pareja',
            'Fecha matrícula', 'Último pago', 'Valor especial', 'Motivo valor especial',
            'Total pagado', 'Debe', 'Saldo a favor',
        ], $filas->all(), ['P', 'R', 'S', 'T'], 'S');
    }

    private function hojaResumen(Spreadsheet $libro, $cursos, $paquetes, $vacacionales): void
    {
        $alumnos = DB::table('alumnos')->where('estado', 1)
            ->whereIn('id', $cursos->pluck('alumno_id')->merge($paquetes->pluck('alumno_id'))->merge($vacacionales->pluck('alumno_id'))->unique())
            ->orderBy('nombres')->orderBy('apellidos')->get();

        $filas = $alumnos->map(function ($a) use ($cursos, $paquetes, $vacacionales) {
            $suyos = $cursos->where('alumno_id', $a->id);
            $debeCursos = $suyos->where('saldo', '>', 0)->sum('saldo');
            $debePaquetes = $paquetes->where('alumno_id', $a->id)->where('saldo', '>', 0)->sum('saldo');
            $debeVacacionales = $vacacionales->where('alumno_id', $a->id)->where('saldo', '>', 0)->sum('saldo');
            return [
                trim("{$a->nombres} {$a->apellidos}"), $a->documento, $a->telefono, $a->correo,
                $suyos->map(fn ($c) => $c->curso . ' (' . (self::DIAS[$c->dia] ?? '') . ' ' . substr((string) $c->hora, 0, 5) . ')')->implode("\n"),
                $suyos->count(),
                $debeCursos, $debePaquetes, $debeVacacionales, $debeCursos + $debePaquetes + $debeVacacionales,
                abs($suyos->where('saldo', '<', 0)->sum('saldo')),
            ];
        });
        $hoja = $this->hoja($libro, 'Resumen por alumno', [
            'Alumno', 'Documento', 'Teléfono', 'Correo', 'Cursos', 'N.º cursos',
            'Debe cursos', 'Debe paquetes', 'Debe vacacionales', 'Total debe', 'Saldo a favor',
        ], $filas->all(), ['G', 'H', 'I', 'J', 'K'], 'J');
        $hoja->getStyle('E2:E' . $hoja->getHighestRow())->getAlignment()->setWrapText(true);
    }

    private function hojaPaquetes(Spreadsheet $libro, $paquetes): void
    {
        $filas = $paquetes->map(fn ($f) => [
            $f->alumno, $f->documento, $f->telefono, $f->correo, $f->plan, $f->fecha_compra, $f->fecha_vencimiento,
            (int) $f->clases_total, (int) $f->clases_usadas, max(0, (int) $f->clases_total - (int) $f->clases_usadas),
            $f->valor, max(0, $f->saldo),
        ]);
        $this->hoja($libro, 'Paquetes', [
            'Alumno', 'Documento', 'Teléfono', 'Correo', 'Plan', 'Fecha compra', 'Vence',
            'Clases', 'Usadas', 'Disponibles', 'Valor', 'Debe',
        ], $filas->all(), ['K', 'L'], 'L');
    }

    private function hojaVacacionales(Spreadsheet $libro, $vacacionales): void
    {
        $filas = $vacacionales->map(fn ($f) => [
            $f->curso, $f->alumno, $f->documento, $f->telefono, $f->correo, $f->fecha_inscripcion,
            $f->valor, max(0, $f->saldo), $f->valor - $f->saldo,
        ]);
        $this->hoja($libro, 'Vacacionales', [
            'Curso vacacional', 'Alumno', 'Documento', 'Teléfono', 'Correo', 'Fecha inscripción', 'Valor', 'Debe', 'Pagado',
        ], $filas->all(), ['G', 'H', 'I'], 'H');
    }

    private function hojaSinCurso(Spreadsheet $libro): void
    {
        $filas = DB::table('alumnos as a')->where('a.estado', 1)
            ->whereNotExists(fn ($q) => $q->from('curso_alumno')->whereColumn('curso_alumno.alumno_id', 'a.id')->where('curso_alumno.estado', 1))
            ->whereNotExists(fn ($q) => $q->from('paquetes_alumno')->whereColumn('paquetes_alumno.alumno_id', 'a.id')->where('paquetes_alumno.estado', 'activo'))
            ->whereNotExists(fn ($q) => $q->from('vacacional_inscripciones')->whereColumn('vacacional_inscripciones.alumno_id', 'a.id')->where('vacacional_inscripciones.estado', 1))
            ->orderBy('a.nombres')->orderBy('a.apellidos')
            ->get()
            ->map(fn ($a) => [trim("{$a->nombres} {$a->apellidos}"), $a->documento, $a->telefono, $a->correo, substr((string) $a->created_at, 0, 10)]);
        $this->hoja($libro, 'Sin curso', ['Alumno', 'Documento', 'Teléfono', 'Correo', 'Creado'], $filas->all());
    }

    /** Hoja con encabezado, filtros, panel fijo, moneda y las columnas de validación al final. */
    private function hoja(Spreadsheet $libro, string $titulo, array $encabezados, array $filas, array $moneda = [], ?string $deuda = null): Worksheet
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle($titulo);
        $encabezados = array_merge($encabezados, self::VALIDACION);
        $filas = array_map(fn ($f) => array_merge(array_values($f), [null, null]), $filas);

        $hoja->fromArray($encabezados, null, 'A1');
        if ($filas) {
            $hoja->fromArray($filas, null, 'A2', true);
        }

        $ultimaCol = Coordinate::stringFromColumnIndex(count($encabezados));
        $ultimaFila = max(2, count($filas) + 1);
        $hoja->getStyle("A1:{$ultimaCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle("A1:{$ultimaCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        // Columnas que llena el dueño, en amarillo.
        $valIni = Coordinate::stringFromColumnIndex(count($encabezados) - 1);
        $hoja->getStyle("{$valIni}1:{$ultimaCol}1")->getFill()->getStartColor()->setRGB('B45309');
        $hoja->getStyle("{$valIni}2:{$ultimaCol}{$ultimaFila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');

        foreach ($moneda as $col) {
            $hoja->getStyle("{$col}2:{$col}{$ultimaFila}")->getNumberFormat()->setFormatCode('"$"#,##0');
        }
        if ($deuda) {
            $hoja->getStyle("{$deuda}2:{$deuda}{$ultimaFila}")->getFont()->setBold(true);
            // Total de la deuda debajo de la tabla.
            $total = $ultimaFila + 2;
            $hoja->setCellValue("{$deuda}{$total}", "=SUM({$deuda}2:{$deuda}{$ultimaFila})");
            $hoja->getStyle("{$deuda}{$total}")->getFont()->setBold(true);
            $hoja->getStyle("{$deuda}{$total}")->getNumberFormat()->setFormatCode('"$"#,##0');
            $anterior = Coordinate::stringFromColumnIndex(max(1, Coordinate::columnIndexFromString($deuda) - 1));
            $hoja->setCellValue("{$anterior}{$total}", 'TOTAL');
            $hoja->getStyle("{$anterior}{$total}")->getFont()->setBold(true);
        }

        foreach (range(1, count($encabezados)) as $i) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $hoja->getColumnDimension($ultimaCol)->setAutoSize(false)->setWidth(40);
        $hoja->setAutoFilter("A1:{$ultimaCol}{$ultimaFila}");
        $hoja->freezePane('B2');

        return $hoja;
    }
}
