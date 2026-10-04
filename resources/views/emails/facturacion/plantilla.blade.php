{{-- Cuerpo editable (plantilla de la academia administradora) dentro del diseño de facturación. --}}
@extends('emails.facturacion.layout', ['color' => $tipo === 'pago' ? '#2E7D32' : (in_array($tipo, ['mora', 'suspension']) ? '#C62828' : '#1A73E8')])

@section('contenido')
    {!! $cuerpo !!}
@endsection

@if($tipo !== 'pago')
    @section('conDatosPago', true)
@endif
