@extends('emails.academia.layout', ['color' => '#1A73E8'])

@section('contenido')
    <p>Te queda <strong>1 clase</strong> de tu paquete <strong>{{ $datos['curso'] }}</strong>
        @if(!empty($datos['fecha_vencimiento'])) (vigente hasta el {{ $datos['fecha_vencimiento'] }}) @endif.</p>
    <p>Si quieres seguir bailando sin interrupciones, pregúntanos por la renovación de tu paquete.</p>
@endsection
