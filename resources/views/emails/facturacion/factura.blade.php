@extends('emails.facturacion.layout', ['color' => '#1A73E8'])

@section('contenido')
    <p>Te compartimos la cuenta de cobro de tu suscripción al ERP.</p>
    @include('emails.facturacion._resumen')
@endsection

@section('conDatosPago', true)
