@extends('emails.facturacion.layout', ['color' => '#C62828'])

@section('contenido')
    <p>Tu cuenta de cobro <strong>{{ $datos['numero'] }}</strong> venció el <strong>{{ $datos['vencimiento'] }}</strong>
        y tienes un saldo pendiente de <strong style="color:#c62828;">${{ number_format($datos['saldo_total'], 0, ',', '.') }}</strong>.</p>
    <p>Para evitar la suspensión del servicio, realiza el pago antes del <strong>{{ $datos['fecha_suspension'] }}</strong>.</p>
    @include('emails.facturacion._resumen')
@endsection

@section('conDatosPago', true)
