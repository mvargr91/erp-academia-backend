{{-- Cuerpo editable de la plantilla de correo de la academia, dentro del diseño común. --}}
@extends('emails.academia.layout', ['color' => '#1A73E8'])

@section('contenido')
    {!! $cuerpo !!}
@endsection
