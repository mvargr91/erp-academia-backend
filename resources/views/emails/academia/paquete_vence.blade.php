@extends('emails.academia.layout', ['color' => '#F9A825'])

@section('contenido')
    <p>Tu paquete <strong>{{ $datos['curso'] }}</strong> vence el <strong>{{ $datos['fecha_vencimiento'] }}</strong>
        y todavía te {{ $datos['restantes'] == 1 ? 'queda' : 'quedan' }}
        <strong>{{ $datos['restantes'] }} de {{ $datos['clases_total'] }} clases</strong>.</p>
    <p>¡Agéndalas antes de esa fecha para no perderlas! Si necesitas ayuda para programarlas, responde este correo.</p>
@endsection
