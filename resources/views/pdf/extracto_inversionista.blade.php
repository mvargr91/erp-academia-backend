<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Extracto de Inversiones</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 12px;
      color: #000;
    }

    .section-title {
      background-color: #e6e6e6;
      font-weight: bold;
      padding: 4px;
      margin-top: 10px;
      border-top: 1px solid #000;
      border-right: 1px solid #000;
      border-left: 1px solid #000;
      border-bottom: none;
    }

    .info-table,
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 5px;
    }

    .info-table td,
    .data-table th,
    .data-table td {
      border: 1px solid #000;
      padding: 5px;
      vertical-align: top;
    }

    .data-table th {
      background-color: #f0f0f0;
    }

    .text-left {
      text-align: left;
    }

    .text-right {
      text-align: right;
    }

    .section-sin-border-bottom {
      border-top: 1px solid #000;
      border-right: 1px solid #000;
      border-left: 1px solid #000;
      border-bottom: none;
    }

    .tabla-sin-borde-superior td {
      border-top: none !important;
    }

    .info-table.sin-borde-superior td {
      border-bottom: none !important;
    }
  </style>
</head>
<body>

  <table width="100%" cellpadding="2" cellspacing="0" style="border-collapse: collapse; margin-bottom: 10px;">
    <tr>
      <td width="5%">
        <img src="{{ public_path('images/logo-login.png') }}" style="width: 30px;">
      </td>
      <td width="55%" style="font-weight: bold; font-size: 14px;">EXTRACTO DE INVERSIONES</td>
      <td width="10%" class="text-right"><strong>Desde:</strong></td>
      <td width="10%" class="text-left">{{ $desde }}</td>
      <td width="5%"></td>
      <td width="10%" class="text-right"><strong>Hasta:</strong></td>
      <td width="10%" class="text-left">{{ $hasta }}</td>
    </tr>
  </table>

  <div class="section-title">Inversionista:</div>
  <table class="info-table" style="margin-top: 0px;">
    <tr>
      <td class="text-left"><strong>Nombre:</strong> {{ $nombre }}</td>
      <td class="text-left"><strong>Documento:</strong> {{ $documento }}</td>
    </tr>
    <tr>
      <td class="text-left"><strong>Correo electrónico:</strong> {{ $email }}</td>
      <td class="text-left"><strong>Fecha generación:</strong> {{ $fechaGeneracion }}</td>
    </tr>
  </table>

  <div class="section-title">Inversiones:</div>
  <table class="data-table" style="margin-top: 0px;">
    <thead>
      <tr>
        <th class="text-left">Proyecto</th>
        <th class="text-left">Fecha</th>
        <th class="text-right">Valor</th>
      </tr>
    </thead>
    <tbody>
      @php $total_inversion = 0; @endphp
      @foreach ($inversiones as $inv)
        <tr>
          <td class="text-left">{{ $inv->codigo_proyecto }}</td>
          <td class="text-left">{{ \Carbon\Carbon::parse($inv->fecha_inversion)->toDateString() }}</td>
          <td class="text-right">{{ number_format($inv->valor_inversion_por_proyecto, 0, ',', '.') }}</td>
        </tr>
        @php $total_inversion += $inv->valor_inversion_por_proyecto; @endphp
      @endforeach
      <tr>
        <td colspan="2" class="text-left"><strong>Total:</strong></td>
        <td class="text-right"><strong>{{ number_format($total_inversion, 0, ',', '.') }}</strong></td>
      </tr>
    </tbody>
  </table>

  @if (isset($transacciones) && count($transacciones) > 0)
    <div class="section-title">Transacciones del mes:</div>
    <table class="data-table" style="margin-top: 0px;">
      <thead>
        <tr>
          <th class="text-left">Proyecto</th>
          <th class="text-left">Fecha</th>
          <th class="text-left">Tipo</th>
          <th class="text-right">Valor</th>
          <th class="text-right">Ret. Fuente</th>
          <th class="text-right">Valor pagado</th>
        </tr>
      </thead>
      <tbody>
        @php
          $total_concepto = 0;
          $total_ret_fuente = 0;
          $total_valor_pagar = 0;
        @endphp
        @foreach ($transacciones as $tran)
          <tr>
            <td class="text-left">{{ $tran->codigo_proyecto }}</td>
            <td class="text-left">{{ \Carbon\Carbon::parse($tran->fecha)->toDateString() }}</td>
            <td class="text-left">{{ $tran->tipo_concepto }}</td>
            <td class="text-right">{{ number_format($tran->valor_concepto, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($tran->valor_ret_fuente, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($tran->valor_a_pagar, 0, ',', '.') }}</td>
          </tr>
          @php
            $total_concepto += $tran->valor_concepto;
            $total_ret_fuente += $tran->valor_ret_fuente;
            $total_valor_pagar += $tran->valor_a_pagar;
          @endphp
        @endforeach
        <tr>
          <td colspan="3" class="text-left"><strong>Total:</strong></td>
          <td class="text-right"><strong>{{ number_format($total_concepto, 0, ',', '.') }}</strong></td>
          <td class="text-right"><strong>{{ number_format($total_ret_fuente, 0, ',', '.') }}</strong></td>
          <td class="text-right"><strong>{{ number_format($total_valor_pagar, 0, ',', '.') }}</strong></td>
        </tr>
      </tbody>
    </table>
  @endif

  @if (isset($rendimientos) && count($rendimientos) > 0)
    <div class="section-title text-left">Vencimientos para mes de {{ $nombreMes }}</div>

    <table class="info-table sin-borde-superior" style="background-color:#f7f7f7; margin-top: 0px;">
      <tr>
        <td colspan="5" style="padding: 8px;">
          <strong>Rendimientos:</strong><br>
          Relacionamos la información para la liquidación de los rendimientos de las inversiones de su portafolio con nuestra compañía para el mes de {{ $nombreMes }}:
        </td>
      </tr>
    </table>

    <table class="data-table section-sin-border-bottom" style="background-color:#f7f7f7; margin-top: 0px;">
      <thead>
        <tr>
          <th class="text-left">Proyecto</th>
          <th class="text-left">Fecha vcmto.</th>
          <th class="text-right">Valor</th>
          <th class="text-right">Ret fuente</th>
          <th class="text-right">Valor a pagar</th>
        </tr>
      </thead>
      <tbody>
        @php
          $total_valor = 0;
          $total_ret = 0;
          $total_pago = 0;
        @endphp
        @foreach ($rendimientos as $r)
          <tr>
            <td class="text-left">{{ $r->codigo_proyecto }}</td>
            <td class="text-left">{{ \Carbon\Carbon::parse($r->fecha_vencimiento)->toDateString() }}</td>
            <td class="text-right">{{ number_format($r->valor_concepto, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($r->valor_ret_fuente, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($r->valor_a_pagar, 0, ',', '.') }}</td>
          </tr>
          @php
            $total_valor += $r->valor_concepto;
            $total_ret += $r->valor_ret_fuente;
            $total_pago += $r->valor_a_pagar;
          @endphp
        @endforeach
        <tr>
          <td colspan="2" class="text-left"><strong>Total:</strong></td>
          <td class="text-right"><strong>{{ number_format($total_valor, 0, ',', '.') }}</strong></td>
          <td class="text-right"><strong>{{ number_format($total_ret, 0, ',', '.') }}</strong></td>
          <td class="text-right"><strong>{{ number_format($total_pago, 0, ',', '.') }}</strong></td>
        </tr>
      </tbody>
    </table>

    <table class="info-table tabla-sin-borde-superior" style="background-color:#f7f7f7; margin-top: 0px;">
      <tr>
        <td style="padding: 8px;">
          En caso que desee retirar el valor de estos rendimientos, emitir la cuenta de cobro antes de la fecha de vencimiento y enviarla al correo <strong>administracion@gsvingenieria.com</strong>.<br>
          Recuerde que si el valor de rendimientos es superior a un salario mínimo mensual legal deberá adjuntarse el pago de seguridad social.
        </td>
      </tr>
    </table>
  @endif

  @if (isset($reintegros) && count($reintegros) > 0)
    <table class="info-table sin-borde-superior" style="background-color:#f7f7f7; margin-top: 5px;">
      <tr>
        <td colspan="3" style="padding: 8px;">
          <strong>Reintegros:</strong><br>
          Relacionamos los reintegros a realizar en el mes de {{ $nombreMes }} por el capital invertido en los siguientes proyectos:
        </td>
      </tr>
    </table>

    <table class="data-table section-sin-border-bottom" style="background-color:#f7f7f7; margin-top: 0px;">
      <thead>
        <tr>
          <th class="text-left">Proyecto</th>
          <th class="text-left">Fecha</th>
          <th class="text-right">Valor</th>
        </tr>
      </thead>
      <tbody>
        @php $total_reintegros = 0; @endphp
        @foreach ($reintegros as $r)
          <tr>
            <td class="text-left">{{ $r->codigo_proyecto }}</td>
            <td class="text-left">{{ \Carbon\Carbon::parse($r->fecha_vencimiento)->toDateString() }}</td>
            <td class="text-right">{{ number_format($r->valor_concepto, 0, ',', '.') }}</td>
          </tr>
          @php $total_reintegros += $r->valor_concepto; @endphp
        @endforeach
        <tr>
          <td colspan="2" class="text-left"><strong>Total:</strong></td>
          <td class="text-right"><strong>{{ number_format($total_reintegros, 0, ',', '.') }}</strong></td>
        </tr>
      </tbody>
    </table>

    <table class="info-table tabla-sin-borde-superior" style="background-color:#f7f7f7; margin-top: 0px;">
      <tr>
        <td style="padding: 8px;">
          Favor informar antes de la fecha de vencimiento en caso que desee retirarlos para realizar los trámites correspondientes.
        </td>
      </tr>
    </table>
  @endif

</body>
</html>
