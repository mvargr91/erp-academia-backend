<?php

namespace App\Services;

class ChartPngService
{
    /**
     * Gráfico tipo ÁREA con distinción de colores (Verde positivo / Rojo negativo)
     * - Baseline (piso) en 0
     * - Eje Y: 5 marcas (ticks) con extremos reales
     * - Sin fuentes externas (GD built-in)
     */
    public function areaChartBase64(
        array $labels,
        array $values,
        int $width = 850,
        int $height = 260,
        string $title = 'FLUJO DE CAJA LIBRE - ACUMULADO',
        string $yAxisLabel = ''
    ): ?string {
        if (!extension_loaded('gd')) return null;
        if (count($labels) === 0 || count($values) === 0) return null;

        $n = min(count($labels), count($values));
        $labels = array_slice($labels, 0, $n);
        $values = array_slice($values, 0, $n);

        $img = imagecreatetruecolor($width, $height);

        // ---- Colores
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        $grid  = imagecolorallocate($img, 210, 210, 210);

        // Colores Positivos (Verde)
        $greenFill = imagecolorallocatealpha($img, 81, 130, 56, 60);
        $greenLine = imagecolorallocate($img, 81, 130, 56);

        // Colores Negativos (Rojo)
        $redFill = imagecolorallocatealpha($img, 220, 50, 50, 60);
        $redLine = imagecolorallocate($img, 200, 0, 0);

        imagefill($img, 0, 0, $white);

        // ---- Configuración de fuentes y márgenes
        $F_TITLE = 5; 
        $F_AXIS  = 3; 
        $F_XLAB  = 2; 
        $PSEUDO_BOLD = true;

        $padL = 110; $padR = 15; $padT = 40; $padB = 60;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;

        $minReal = (float)min($values);
        $maxReal = (float)max($values);

        // El eje siempre debe considerar el 0 para el dibujo del área
        $minAxis = min($minReal, 0.0);
        $maxAxis = max($maxReal, 0.0);
        if ($minAxis === $maxAxis) { $minAxis -= 1; $maxAxis += 1; }

        $ticks = $this->ticksWithRealExtremes($minAxis, $maxAxis, $minReal, $maxReal, 5);

        // ---- Funciones de mapeo
        $toX = function (int $idx) use ($n, $padL, $plotW) {
            if ($n <= 1) return $padL;
            return $padL + (int)round($plotW * ($idx / ($n - 1)));
        };

        $toY = function (float $v) use ($minAxis, $maxAxis, $padT, $plotH) {
            $range = $maxAxis - $minAxis;
            $t = ($v - $minAxis) / $range;
            return $padT + (int)round($plotH * (1 - $t));
        };

        $y0 = $toY(0.0);

        // ---- Dibujar Grid y Etiquetas Y
        $hAxis = imagefontheight($F_AXIS);
        foreach ($ticks as $tv) {
            $y = $toY((float)$tv);
            imageline($img, $padL, $y, $padL + $plotW, $y, $grid);
            
            $label = $this->formatCOP((float)$tv);
            $color = ($tv < 0) ? $redLine : $black;
            imagestring($img, $F_AXIS, 5, (int)($y - $hAxis / 2), $label, $color);
        }

        // ---- Dibujar Áreas y Líneas (Segmentado por color)
        for ($i = 0; $i < $n - 1; $i++) {
            $x1 = $toX($i);
            $y1 = $toY((float)$values[$i]);
            $x2 = $toX($i + 1);
            $y2 = $toY((float)$values[$i + 1]);

            // Lógica de color: si el punto de origen es negativo, usamos rojo
            $isNeg = ($values[$i] < 0);
            $fill = $isNeg ? $redFill : $greenFill;
            $line = $isNeg ? $redLine : $greenLine;

            // Polígono del segmento actual (Trapecio hacia el cero)
            $points = [
                $x1, $y1,
                $x2, $y2,
                $x2, $y0,
                $x1, $y0
            ];
            imagefilledpolygon($img, $points, 4, $fill);
            
            // Línea de tendencia
            imageline($img, $x1, $y1, $x2, $y2, $line);
        }

        // ---- Ejes principales (Sobrescriben el área si se solapan)
        imageline($img, $padL, $padT, $padL, $padT + $plotH, $black); // Eje Y
        imageline($img, $padL, $y0, $padL + $plotW, $y0, $black);     // Eje X (en el cero)

        // ---- Etiquetas X
        $step = ($n > 25) ? 2 : 1;
        $wX = imagefontwidth($F_XLAB);
        for ($i = 0; $i < $n; $i += $step) {
            $x = $toX($i);
            $lab = (string)$labels[$i];
            $lw = strlen($lab) * $wX;
            imagestring($img, $F_XLAB, (int)($x - $lw / 2), $padT + $plotH + 12, $lab, $black);
        }

        // ---- Título
        $titleW = strlen($title) * imagefontwidth($F_TITLE);
        $tx = (int)($width / 2 - $titleW / 2);
        imagestring($img, $F_TITLE, $tx, 10, $title, $black);
        if ($PSEUDO_BOLD) imagestring($img, $F_TITLE, $tx + 1, 10, $title, $black);

        // ---- Output
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png ? 'data:image/png;base64,' . base64_encode($png) : null;
    }

    private function ticksWithRealExtremes(float $minAxis, float $maxAxis, float $minReal, float $maxReal, int $count): array {
        $count = max(2, $count);
        $ticks = [];
        $step = ($maxAxis - $minAxis) / ($count - 1);
        for ($i = 0; $i < $count; $i++) { $ticks[] = $minAxis + ($step * $i); }
        $ticks[0] = $minReal;
        $ticks[$count - 1] = $maxReal;
        sort($ticks);
        $ticks = array_values(array_unique(array_map(fn($v) => round((float)$v, 2), $ticks)));
        return $ticks;
    }

    private function formatCOP(float $value): string {
        if (class_exists(\NumberFormatter::class)) {
            $fmt = new \NumberFormatter('es_CO', \NumberFormatter::DECIMAL);
            $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
            return $fmt->format($value);
        }
        return number_format($value, 0, ',', '.');
    }
}