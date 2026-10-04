@extends('emails.academia.layout', ['color' => '#2E7D32'])

@section('contenido')
    <p>¡Gracias! Recibimos tu pago de <strong>${{ number_format($datos['valor'] ?? 0, 0, ',', '.') }}</strong>
        por {{ $datos['concepto'] ?? '' }}.</p>
    <p>Saldo pendiente: <strong>${{ number_format($datos['saldo'] ?? 0, 0, ',', '.') }}</strong>.</p>
@endsection
