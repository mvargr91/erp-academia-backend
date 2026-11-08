<?php

namespace App\Services\Academia;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

/**
 * Excel para que el dueño de la academia valide sus datos: alumnos por curso con lo que debe,
 * resumen por alumno, paquetes, vacacionales y alumnos sin curso. Exportar solo lee.
 * Cada hoja trae las columnas "¿Correcto?" y "Corrección" vacías para que las llene.
 *
 * El mismo archivo se puede volver a subir con correcciones (ImportacionCorrecciones): en
 * "Alumnos por curso" se editan las columnas azules (pareja, valor especial, retirar) y en
 * "Nuevas matrículas" se agregan alumnos a cursos. Cada matrícula lleva su id en una columna oculta.
 *
 * Trabaja sobre la academia activa (BD actual). Lo usan el comando academia:exportar-alumnos
 * y la descarga de la pantalla Alumnos.
 */
class ExportacionAlumnos
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    private const VALIDACION = ['¿Correcto? (Sí/No)', 'Corrección'];

    // Nombres de hojas y columnas que lee la importación de correcciones.
    public const HOJA_CURSOS = 'Alumnos por curso';
    public const HOJA_NUEVAS = 'Nuevas matrículas';
    public const COL_ID = 'ID matrícula';
    public const COL_ALUMNO = 'Alumno';
    public const COL_CURSO = 'Curso';
    public const COL_PAREJA = 'Pareja';
    public const COL_VALOR_ESPECIAL = 'Valor especial';
    public const COL_MOTIVO = 'Motivo valor especial';
    public const COL_RETIRAR = 'Retirar (Sí)';
    private const FILAS_NUEVAS = 300;

    private int $totalAlumnos = 0;
    private int $totalCursos = 0;

    /** "Ana Pérez [#12]": el id al final permite reconocer al alumno o curso al subir el archivo. */
    public static function etiqueta(string $texto, int $id): string
    {
        return trim($texto) . " [#{$id}]";
    }

    /** Guarda el Excel en $archivo y devuelve un resumen (conteos y deudas). */
    public function generar(string $archivo, string $titulo = 'Alumnos'): array
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setTitle($titulo);
        $libro->removeSheetByIndex(0);

        $cursos = $this->alumnosPorCurso();
        $paquetes = $this->paquetes();
        $vacacionales = $this->vacacionales();

        $this->hojaInstrucciones($libro);
        $this->hojaListas($libro);
        $this->hojaPorCurso($libro, $cursos);
        $this->hojaNuevas($libro);
        $this->hojaResumen($libro, $cursos, $paquetes, $vacacionales);
        $this->hojaPaquetes($libro, $paquetes);
        $this->hojaVacacionales($libro, $vacacionales);
        $this->hojaSinCurso($libro);
        // Las listas de los desplegables van al final y ocultas.
        $listas = $libro->getSheetByName('Listas');
        $libro->removeSheetByIndex($libro->getIndex($listas));
        $libro->addSheet($listas);
        $listas->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        $libro->setActiveSheetIndex(1);

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
                'ca.id as matricula_id', 'ca.pareja_alumno_id', 'c.modalidad_id',
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
                // Lo que se le cobra hoy por ciclo (o mes): tarifa según orden de cursos, pareja o valor especial.
                $f->precio = Tarifas::cobra((int) $f->modalidad_id) ? Tarifas::precio((int) $f->matricula_id) : null;
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
            $f->alumno, $f->documento, $f->telefono, $f->correo,
            $f->fecha_matricula, $f->ultima_fecha_pago,
            $f->precio ? $f->precio['valor'] : null, $f->precio ? $f->precio['regla'] : 'Sin tarifa',
            $f->pareja_alumno_id ? self::etiqueta($f->pareja, (int) $f->pareja_alumno_id) : null,
            $f->valor_especial !== null ? (float) $f->valor_especial : null, $f->valor_especial_motivo, null,
            $f->pagado, $f->saldo > 0 ? $f->saldo : 0, $f->saldo < 0 ? -$f->saldo : 0,
            (int) $f->matricula_id,
        ]);
        $hoja = $this->hoja($libro, self::HOJA_CURSOS, [
            'Curso', 'Ritmo', 'Día', 'Hora', 'Profesor', 'Sede', 'Modalidad', 'Curso activo',
            self::COL_ALUMNO, 'Documento', 'Teléfono', 'Correo',
            'Fecha matrícula', 'Último pago', 'Precio por ciclo', 'Cómo se calcula',
            self::COL_PAREJA, self::COL_VALOR_ESPECIAL, self::COL_MOTIVO, self::COL_RETIRAR,
            'Total pagado', 'Debe', 'Saldo a favor',
            self::COL_ID,
        ], $filas->all(), ['O', 'R', 'U', 'V', 'W'], 'V', 1);

        // Columnas que se pueden corregir y volver a subir, en azul.
        $ultima = max(2, $cursos->count() + 1);
        $this->editables($hoja, ['Q', 'R', 'S', 'T'], $ultima);
        $this->listaDesplegable($hoja, "Q2:Q{$ultima}", "Listas!\$A\$2:\$A\$" . ($this->totalAlumnos + 1));
        $this->listaDesplegable($hoja, "T2:T{$ultima}", '"Sí"');
    }

    /** Filas vacías para matricular alumnos en cursos (con desplegables de alumnos y cursos). */
    private function hojaNuevas(Spreadsheet $libro): void
    {
        $hoja = $this->hoja($libro, self::HOJA_NUEVAS, [
            self::COL_ALUMNO, self::COL_CURSO, self::COL_PAREJA, self::COL_VALOR_ESPECIAL, self::COL_MOTIVO,
        ], [], [], null, 0, false);
        $ultima = self::FILAS_NUEVAS + 1;
        $this->editables($hoja, ['A', 'B', 'C', 'D', 'E'], $ultima);
        $alumnos = "Listas!\$A\$2:\$A\$" . ($this->totalAlumnos + 1);
        $this->listaDesplegable($hoja, "A2:A{$ultima}", $alumnos);
        $this->listaDesplegable($hoja, "B2:B{$ultima}", "Listas!\$B\$2:\$B\$" . ($this->totalCursos + 1));
        $this->listaDesplegable($hoja, "C2:C{$ultima}", $alumnos);
        $hoja->getStyle("D2:D{$ultima}")->getNumberFormat()->setFormatCode('"$"#,##0');
        foreach (['A' => 45, 'B' => 50, 'C' => 45, 'D' => 16, 'E' => 35] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setAutoSize(false)->setWidth($ancho);
        }
    }

    /** Valores de los desplegables: alumnos activos y cursos activos, con su id. */
    private function hojaListas(Spreadsheet $libro): void
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle('Listas');
        $alumnos = DB::table('alumnos')->where('estado', 1)->orderBy('nombres')->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos', 'documento'])
            ->map(fn ($a) => [self::etiqueta(trim("{$a->nombres} {$a->apellidos}") . ($a->documento ? " · {$a->documento}" : ''), (int) $a->id)]);
        $cursos = DB::table('cursos as c')->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->where('c.estado', 1)->where('c.activo', 1)->orderBy('c.dia')->orderBy('c.hora')
            ->get(['c.id', DB::raw('COALESCE(c.nombre, r.nombre) as nombre'), 'c.dia', 'c.hora', 's.nombre as sede'])
            ->map(fn ($c) => [self::etiqueta($c->nombre . ' · ' . (self::DIAS[$c->dia] ?? '') . ' ' . substr((string) $c->hora, 0, 5)
                . ($c->sede ? " · {$c->sede}" : ''), (int) $c->id)]);
        $this->totalAlumnos = $alumnos->count();
        $this->totalCursos = $cursos->count();
        $hoja->fromArray([['Alumnos', 'Cursos']], null, 'A1');
        if ($alumnos->isNotEmpty()) {
            $hoja->fromArray($alumnos->all(), null, 'A2');
        }
        if ($cursos->isNotEmpty()) {
            $hoja->fromArray($cursos->all(), null, 'B2');
        }
    }

    private function hojaInstrucciones(Spreadsheet $libro): void
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle('Instrucciones');
        $lineas = [
            ['CÓMO VALIDAR Y CORREGIR ESTE ARCHIVO'],
            [''],
            ['1. Revise cada hoja. En las columnas amarillas "¿Correcto?" y "Corrección" puede anotar lo que esté mal (solo son notas: el sistema no las lee).'],
            ['2. Para que el sistema aplique un cambio, use las columnas AZULES:'],
            ['   Hoja "Alumnos por curso":'],
            ['   • Pareja: elija de la lista con quién paga en pareja ese curso (debe estar matriculado en el mismo curso). Bórrela para que pague individual.'],
            ['   • Valor especial y Motivo: valor pactado por ciclo en lugar de la tarifa (beca, convenio). Bórrelo para que vuelva a pagar la tarifa.'],
            ['   • Retirar (Sí): retira al alumno de ese curso. Solo se puede si no tiene saldo pendiente ni a favor en ese curso.'],
            ['   Hoja "Nuevas matrículas": elija el alumno y el curso de las listas (y si aplica la pareja y el valor especial).'],
            ['3. No cambie las demás columnas ni borre filas: lo que no esté en azul no se tiene en cuenta.'],
            ['4. Suba el archivo en Alumnos → "Subir correcciones". Antes de guardar, el sistema muestra todos los cambios y cómo queda el precio de cada alumno.'],
            [''],
            ['El precio nunca se escribe a mano: el sistema lo calcula con las tarifas según cuántos cursos tiene el alumno, si paga en pareja o si tiene valor especial.'],
            ['Los cambios también corrigen el saldo del ciclo actual con la diferencia de precio. La deuda no se edita en este archivo (use pagos o condonaciones).'],
        ];
        $hoja->fromArray($lineas, null, 'A1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->getColumnDimension('A')->setWidth(150);
    }

    private function editables(Worksheet $hoja, array $columnas, int $ultimaFila): void
    {
        foreach ($columnas as $col) {
            $hoja->getStyle("{$col}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0369A1');
            $hoja->getStyle("{$col}2:{$col}{$ultimaFila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0F2FE');
        }
    }

    private function listaDesplegable(Worksheet $hoja, string $rango, string $formula): void
    {
        $validacion = new DataValidation();
        $validacion->setType(DataValidation::TYPE_LIST);
        $validacion->setErrorStyle(DataValidation::STYLE_STOP);
        $validacion->setAllowBlank(true);
        $validacion->setShowDropDown(true);
        $validacion->setShowErrorMessage(true);
        $validacion->setErrorTitle('Valor no válido');
        $validacion->setError('Elija un valor de la lista.');
        $validacion->setFormula1($formula);
        $hoja->setDataValidation($rango, $validacion);
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

    /**
     * Hoja con encabezado, filtros, panel fijo, moneda y las columnas de validación al final.
     * $ocultas: cuántas de las últimas columnas de $encabezados van ocultas (después de las de validación).
     */
    private function hoja(Spreadsheet $libro, string $titulo, array $encabezados, array $filas, array $moneda = [], ?string $deuda = null,
        int $ocultas = 0, bool $validacion = true): Worksheet
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle($titulo);
        $extra = $validacion ? self::VALIDACION : [];
        $corte = count($encabezados) - $ocultas;
        $encabezados = array_merge(array_slice($encabezados, 0, $corte), $extra, array_slice($encabezados, $corte));
        $filas = array_map(function ($f) use ($corte, $extra) {
            $f = array_values($f);
            return array_merge(array_slice($f, 0, $corte), array_fill(0, count($extra), null), array_slice($f, $corte));
        }, $filas);

        $hoja->fromArray($encabezados, null, 'A1');
        if ($filas) {
            $hoja->fromArray($filas, null, 'A2', true);
        }

        $visibles = count($encabezados) - $ocultas;
        $ultimaCol = Coordinate::stringFromColumnIndex($visibles);
        $ultimaFila = max(2, count($filas) + 1);
        $hoja->getStyle("A1:{$ultimaCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hoja->getStyle("A1:{$ultimaCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        if ($validacion) {
            // Columnas de notas del dueño, en amarillo.
            $valIni = Coordinate::stringFromColumnIndex($visibles - 1);
            $hoja->getStyle("{$valIni}1:{$ultimaCol}1")->getFill()->getStartColor()->setRGB('B45309');
            $hoja->getStyle("{$valIni}2:{$ultimaCol}{$ultimaFila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
        }

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

        foreach (range(1, $visibles) as $i) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        if ($validacion) {
            $hoja->getColumnDimension($ultimaCol)->setAutoSize(false)->setWidth(40);
        }
        for ($i = $visibles + 1; $i <= count($encabezados); $i++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setVisible(false);
        }
        $hoja->setAutoFilter("A1:{$ultimaCol}{$ultimaFila}");
        $hoja->freezePane('B2');

        return $hoja;
    }
}
