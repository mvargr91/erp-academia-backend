{{--
    Correo de cumpleaños: dentro del diseño común (logo y colores de la academia, Configuración →
    Apariencia) lleva una tarjeta de celebración con el nombre del alumno en grande. El mensaje de abajo
    es el de la plantilla «Feliz cumpleaños» (Configuración → Plantillas de correo), editable por la academia.
    Sin plantilla ($cuerpo vacío) usa un mensaje por defecto.
--}}
@php
    $m = $marca ?? \App\Support\Academias\Apariencia::paraCorreo();
    $nombre = trim((string) ($datos['alumno'] ?? ''));
    $primerNombre = $nombre !== '' ? explode(' ', $nombre)[0] : '';
    $cuerpo = $cuerpo ?? '<p>Todo el equipo de <strong>' . e($academia['nombre'] ?? '') . '</strong> te desea un muy feliz cumpleaños.</p>'
        . '<p>¡Que lo celebres bailando! Te esperamos en clase.</p>';
@endphp
@extends('emails.academia.layout', ['color' => $m['color_acento'], 'sinSaludo' => true])

@section('contenido')
    {{-- Tarjeta de celebración: degradado con los colores de la academia (color sólido en los correos que no muestran degradados). --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;">
        <tr>
            <td align="center" style="background-color:{{ $m['color_primario'] }}; background-image:linear-gradient(135deg, {{ $m['color_primario'] }} 0%, {{ $m['color_acento'] }} 100%); border-radius:14px; padding:30px 20px 26px; color:#ffffff; text-align:center;">
                <div style="font-size:24px; letter-spacing:4px; line-height:1;">🎈 🎉 🎁 🎉 🎈</div>
                <div style="font-size:58px; line-height:1.15; margin:14px 0 6px;">🎂</div>
                <div style="font-size:30px; font-weight:bold; line-height:1.2; margin:0;">¡Feliz cumpleaños!</div>
                @if($primerNombre !== '')
                    <div style="font-size:24px; line-height:1.3; margin-top:6px; font-weight:bold;">{{ $primerNombre }}</div>
                @endif
                <div style="font-size:14px; line-height:1.4; margin-top:12px; opacity:0.9;">Hoy celebramos contigo ✨</div>
            </td>
        </tr>
    </table>

    {{-- Mensaje de la academia (editable). --}}
    <div style="text-align:center; font-size:16px; line-height:1.7; color:#333; padding:22px 6px 4px;">
        {!! $cuerpo !!}
    </div>

    <div style="text-align:center; font-size:22px; letter-spacing:8px; padding:6px 0 2px;">💃 🎶 🕺</div>
@endsection
