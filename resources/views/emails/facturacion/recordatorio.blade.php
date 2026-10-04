@extends('emails.facturacion.layout', ['color' => '#F9A825'])

@section('contenido')
    <p>Te recordamos que tu cuenta de cobro <strong>{{ $datos['numero'] }}</strong> vence el
        <strong>{{ $datos['vencimiento'] }}</strong>.</p>
    @include('emails.facturacion._resumen')
@endsection

@section('conDatosPago', true)
