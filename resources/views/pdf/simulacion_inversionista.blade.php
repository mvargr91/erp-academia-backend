<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { size: A4 portrait; margin: 8mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }

.title { font-size: 14px; font-weight: 800; margin-bottom: 4px; text-transform: uppercase; }
.label { font-weight: 700; padding: 1px 1px;  }
.input { background: #d9d9d9; text-align: center; font-weight: 700; padding: 1px 1px; }

/* =========================
   SOLUCIÓN DOMPDF: wrapper con radio
   ========================= */
.table-wrap{
  border: 1px solid #000;
  border-radius: 8px;
  overflow: hidden;    
  margin-top: 4px;
}

.table-wrap table{
  width: 100%;
  border-collapse: separate; 
  border-spacing: 0;       
  table-layout: fixed;
}

/* Bordes internos */
.table-wrap th,
.table-wrap td{
  border: 1px solid #000;
  padding: 1px 1px;
  vertical-align: middle;
}

/* Opcional: evita doble */
.table-wrap table tr:first-child th,
.table-wrap table tr:first-child td { border-top: 0; }
.table-wrap table tr:last-child td { border-bottom: 0; }
.table-wrap table tr td:first-child,
.table-wrap table tr th:first-child { border-left: 0; }
.table-wrap table tr td:last-child,
.table-wrap table tr th:last-child { border-right: 0; }

/* Si alguna tabla debía ir sin bordes (tu clase no-border), mantenemos eso */
.no-border td { border: none !important; }
.no-border th { border: none !important; }

/* Tabla conceptos */
.conceptos th { background: #f3f3f3; font-weight: 800; text-align: center;  font-size: 10px; text-transform: uppercase; padding: 1px 3px; }
.conceptos td { text-align: right; }
.conceptos td:first-child { text-align: left; }

.indent-1 { padding-left: 12px; }
.bold { font-weight: 800; }

/* Evitar cortes feos */
tr, td, th { page-break-inside: avoid; }

/* Gráfica */
.chart-wrap { margin-top: 6px; border: 1px solid #000; padding: 4px; border-radius: 12px; overflow: hidden; }
.chart-title { font-weight: 800; text-align: center; margin-bottom: 3px; }
.chart-img { width: 100%; height: 200px; object-fit: contain; }
</style>
</head>

<body>

<div class="title">{{ $titulo }}</div>

<!-- =========================
     TABLA 1: PROYECTO
     ========================= -->
<div class="table-wrap">
  <table>
    <tr>
      <td width="45%">
        <div class="label">Proyecto</div>
        <div class="input">{{ $nombre_proyecto }}</div>
      </td>
      <td width="25%">
        <div class="label">Código proyecto</div>
        <div class="input">{{ $codigo_proyecto }}</div>
      </td>
      <td width="15%">
        <div class="label">Valor total proyecto</div>
        <div class="input">{{ number_format($valor_total_proyecto, 0, ',', '.') }}</div>
      </td>
      <td width="15%">
        <div class="label">Potencia kWp</div>
        <div class="input">{{ number_format($potencia_kwp,0,',','.') }}</div>
      </td>
    </tr>
  </table>
</div>
<!-- <div class="table-wrap">
  <table>
    <tr>
      <td width="50%">
        <div class="label">Nombre</div>
        <div class="input">{{ $nombre_inversionista }}</div>
      </td>
      <td width="50%">
        <div class="label">Correo electrónico</div>
        <div class="input">{{ $email_inversionista }}</div>
      </td>
      <td width="20%">
        <div class="label">Teléfono</div>
        <div class="input">{{ $telefono_inversionista }}</div>
      </td>
      
    </tr>
  </table>
</div> -->

<!-- =========================
     TABLA 2: PARAMETROS / KPI
     ========================= -->
<div class="table-wrap">  
  <table>
    <tr>
      <td>
        <div class="label">Inversionista</div>
        <div class="input">{{ $nombre_inversionista }}</div>
      </td>
      <td>
        <div class="label">Correo electrónico</div>
        <div class="input">{{ $email_inversionista }}</div>
      </td>
      <td>
        <div class="label">Teléfono</div>
        <div class="input">{{ $telefono_inversionista }}</div>
      </td>      
    </tr>
  </table>
    <table>
      <tr>
        <td>
          <div class="label">Fecha</div>
          <div class="input">{{ $fecha }}</div>
        </td>
        <td>
          <div class="label">Valor Inversión</div>
          <div class="input">{{ number_format($valor_part_inversionista, 0, ',', '.') }}</div>
        </td>
        <td>
          <div class="label">Porcentaje Participación (%)</div>
          <div class="input">{{ number_format($porcentaje_part_inversionista,2,',','.') }}</div>
        </td>
      </tr>
    </table>  
    <table>
      <tr>
        <td>
          <div class="label">VPN</div>
          <div class="input">{{ number_format($vpn,2,',','.') }}</div>
        </td>
        <td>
          <div class="label">TIR</div>
          <div class="input">{{ number_format($tir,2,',','.') }}</div>
        </td>
        <td >
          <div class="label">PBT (años)</div>
          <div class="input">{{ $pbt }}</div>
        </td>
      </tr>
    </table>
</div>


<!-- =========================
     TABLA 3: CONCEPTOS
     ========================= -->
<div class="table-wrap" style="margin-top:5px;">
  <table class="conceptos">
    <colgroup>
      <col style="width: 42%">
      @php
        $n = max(count($columnas), 1);
        $w = 58 / $n;
      @endphp
      @foreach($columnas as $c)
        <col style="width: {{ $w }}%">
      @endforeach
    </colgroup>

    <thead>
      <tr>
        <th style="text-align:left; ">Concepto</th>
        @foreach($columnas as $c)
          <th >{{ $c['label'] }}</th>
        @endforeach
      </tr>
    </thead>

    <tbody>
      @foreach($filas as $f)
        <tr>
          <td class="{{ ($f['indent'] ?? 0) ? 'indent-1' : '' }}" style="text-transform: lowercase; ">
            {{ $f['label'] }}
          </td>

          @foreach($f['values'] as $v)
            <td>
              @if($v === null)
                &nbsp; {{-- no totaliza (R, P, etc) --}}
              @else
                {{ number_format((float)$v, 0, ',', '.') }}
                @if(strtoupper((string)($f['tipo_valor'] ?? '')) === 'R')
                  %
                @endif
              @endif
            </td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<!-- =========================
     GRAFICA
     ========================= -->
@if(!empty($chart_base64))
  <div class="chart-wrap">
    <div class="chart-title">FLUJO DE CAJA LIBRE - ACUMULADO</div>
    <img class="chart-img" src="{{ $chart_base64 }}" alt="Grafica flujo caja libre acumulado">
  </div>
@endif

</body>
</html>
