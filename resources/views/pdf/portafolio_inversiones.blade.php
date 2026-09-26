<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Portafolio de Inversiones</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 11px;
      color: #000;
    }

    /* ENCABEZADO */
    .header-table {
      width: 100%;
      border-bottom: 2px solid #333;
      margin-bottom: 15px;
      border: none;
    }

    .header-table td {
      vertical-align: middle;
      text-align: center;
      border: none !important;
    }

    .header-left img {
      height: 100px;
      margin: 0px;
      padding: 0px;
      left: -30px;
      position: relative;
      /* float: left; */
    }

    .header-center {
      font-size: 18px;
      font-weight: bold;
      text-transform: uppercase;
      /* line-height: 1.4; */
      color: #1a1a1a;
      text-align: center;
    }

    .header-right {
      text-align: right;
      font-size: 12px;
    }

    .header-right img {
      height: 40px;
      margin-bottom: 10px;
    }

    .fecha {
      font-weight: bold;
      font-size: 11px;
      color: #444;
      display: block;
    }

    /* TABLA */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th, td {
      border: 1px solid #000;
      padding: 4px;
    }

    th.left, td.left {
      text-align: left;
    }

    th.right, td.right {
      text-align: right;
    }

    th.center, td.center {
      text-align: center;
    }

    th {
      background-color: #f2f2f2;
      font-weight: bold;
    }

    /* TÉRMINOS Y CONDICIONES */
    .terminos {
      font-size: 12px;
      line-height: 1.6;
      border: 1px solid #ccc;
      padding: 20px;
      border-radius: 6px;
      margin-top: 20px;
    }

    .terminos img {
      width: 120px;
      margin-bottom: 10px;
    }

    .terminos h3 {
      text-align: center;
      margin-bottom: 10px;
    }

    .terminos ol {
      padding-left: 20px;
      margin-top: 0;
    }

    .terminos li {
      margin-top: 10px;
    }

    .terminos ul {
      list-style-type: disc;
      margin-top: 5px;
      margin-bottom: 5px;
    }

    .terminos ul li {
      margin-left: 15px;
    }

    .advertencia {
      color: #000;
    }
  </style>
</head>
<body>

  <!-- ENCABEZADO CON TABLA -->
  <table class="header-table"  style="border: none;">
    <tr>
      <td class="header-left" style="width: 30%;">
        <img src="{{ public_path('images/portafolio.png') }}" alt="Panel solar">
      </td>
      <td class="header-center" style="width: 50%;">
        PORTAFOLIO DE INVERSIONES
      </td>
      <td class="header-right" style="width: 20%;">
        <img src="{{ public_path('images/logo-login.png') }}" alt="Logo"><br>
        <span class="fecha">Fecha: {{ $fecha }}</span>
      </td>
    </tr>
  </table>

  <!-- TABLA DE PROYECTOS -->
  <table>
    <thead>
      <tr>
        <th class="left">Proyecto</th>
        <th class="left">Ciudad</th>
        <th class="left">Sector</th>
        <th class="right">Potencia (Kwp)</th>
        <th class="center">Plazo (meses)</th>
        <th class="right">Tasa (E.M)</th>
        <th class="right">Valor Inversión</th>
        <th class="right">Valor Utilidad</th>
        <th class="right">Valor a Liquidar</th>
      </tr>
    </thead>
    <tbody>
      @foreach($proyectos as $item)
        <tr>
          <td class="left">{{ $item['codigo_proyecto'] }}</td>
          <td class="left">{{ $item['ciudad'] }}</td>
          <td class="left">{{ $item['sector'] }}</td>
          <td class="right">{{ $item['potencia'] }}</td>
          <td class="center">{{ $item['plazo'] }}</td>
          <td class="right">{{ $item['tasa'] }}</td>
          <td class="right">${{ $item['valor_inversion'] }}</td>
          <td class="right">${{ $item['valor_utilidad'] }}</td>
          <td class="right">${{ $item['valor_liquidar'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <!-- SALTO DE PÁGINA -->
  <div style="page-break-before: always;"></div>

  <!-- TÉRMINOS Y CONDICIONES -->
  <div class="terminos">
    <img src="{{ public_path('images/logo-login.png') }}" alt="Panel solar" style="width: 10%;">

    <h3>TÉRMINOS Y CONDICIONES</h3>

    <ol>
      <li>
        <strong>La inversión se retornará de la siguiente forma:</strong>
        <ul>
          <li><strong>Capital:</strong> Una vez cumplidos {{ $proyectos[0]['plazo_capital'] }} meses calendario.</li>
          <li><strong>Rentabilidad:</strong> En {{ $proyectos[0]['plazo_interes'] - $proyectos[0]['plazo_capital'] }} cuotas mensuales iguales en los siguientes {{ $proyectos[0]['plazo_interes'] - $proyectos[0]['plazo_capital'] }} meses después del vencimiento de capital.</li>
        </ul>
      </li>

      <li>
        <strong>Impuestos:</strong> Se aplicarán las retenciones de ley sobre la rentabilidad del capital, y estas serán certificadas al final del año contable.
      </li>

      <li>
        <strong>Documentos requeridos del inversionista:</strong>
        <ul style="list-style-type: lower-alpha;">
          <li>Copia de la cédula.</li>
          <li>RUT.</li>
          <li>Certificación bancaria.</li>
        </ul>
      </li>

      <li>
        <strong>Recaudo:</strong> Las consignaciones se deberán realizar en la {{ $cuentaInversion }}a nombre de <strong>{{ $compania->nombre }}</strong> con <strong> NIT {{ $compania->numero_nit }}-{{ $compania->digito_verificacion }}</strong>. <br>
        <span class="advertencia">Por ningún motivo se aceptará dinero en efectivo o en otras cuentas.</span>
      </li>
    </ol>
  </div>

</body>
</html>
