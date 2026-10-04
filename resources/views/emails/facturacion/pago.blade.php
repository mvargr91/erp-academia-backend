@extends('emails.facturacion.layout', ['color' => '#2E7D32'])

@section('contenido')
    <p>Recibimos tu pago de <strong>${{ number_format($datos['pago_valor'], 0, ',', '.') }}</strong>
        del {{ $datos['pago_fecha'] }}, aplicado a la cuenta de cobro <strong>{{ $datos['numero'] }}</strong>. ¡Gracias!</p>
    @if($datos['saldo_total'] > 0)
        <p>Saldo pendiente total: <strong>${{ number_format($datos['saldo_total'], 0, ',', '.') }}</strong>.</p>
    @else
        <p>Tu cuenta está al día.</p>
    @endif
@endsection
