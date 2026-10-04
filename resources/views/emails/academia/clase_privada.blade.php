@extends('emails.academia.layout', ['color' => '#1A73E8'])

@section('contenido')
    <p>Te recordamos tu clase personalizada: <strong>{{ $datos['fecha'] ?? '' }}</strong> a las <strong>{{ $datos['hora'] ?? '' }}</strong>
        @if(!empty($datos['profesor'])) con {{ $datos['profesor'] }} @endif.</p>
    <p>Si no puedes asistir, avísanos con anticipación.</p>
@endsection
