@extends('emails.academia.layout')

@section('contenido')
    <p>¡Te damos la bienvenida a <strong>{{ $academia['nombre'] }}</strong>! Ya quedaste registrado(a) como alumno(a).</p>
    <p>Cuando te matricules en un curso te enviaremos el horario, tu primera clase y los datos de pago.</p>
@endsection
