@extends('emails.facturacion.layout', ['color' => '#C62828'])

@section('contenido')
    <p>Como la cuenta de cobro <strong>{{ $datos['numero'] }}</strong> lleva más de {{ $datos['dias_gracia'] }} días vencida,
        el acceso al ERP de <strong>{{ $datos['academia'] }}</strong> fue <strong>suspendido</strong>.</p>
    <p>Tu información está segura y no se ha borrado nada. En cuanto registremos el pago de
        <strong>${{ number_format($datos['saldo_total'], 0, ',', '.') }}</strong>, el acceso se reactiva automáticamente.</p>
@endsection

@section('conDatosPago', true)
