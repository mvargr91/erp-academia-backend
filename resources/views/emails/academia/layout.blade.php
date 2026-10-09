{{--
    Diseño común de los correos de la academia, con su marca (Configuración → Apariencia):
    cabecera con el logo y los colores de la academia, y una franja del color del tipo de correo
    ($color: rojo en mora, verde en pago…; si la vista no lo indica, el color primario).
--}}
@php
    $marca = $marca ?? \App\Support\Academias\Apariencia::paraCorreo();
    // Correos que no arman los datos de la academia (restablecer contraseña): se toman de la academia activa.
    if (!isset($academia)) {
        $activa = \App\Support\Academias\GestorAcademias::actual();
        $academia = ['nombre' => $activa?->nombre ?? config('app.name'), 'correo' => $activa?->correo, 'telefono' => $activa?->telefono];
    }
    // En un correo real el logo va incrustado (se ve aunque el cliente no descargue imágenes externas);
    // en la vista previa y en los envíos armados como HTML suelto, por su dirección pública.
    $logo = $marca['logo_ruta'] ? (isset($message) ? $message->embed($marca['logo_ruta']) : $marca['logo_url']) : null;
    $franja = $color ?? $marca['color_primario'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background:#f4f5f7; font-family: Arial, Helvetica, sans-serif; color:#333;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7; padding:24px 0;">
    <tr>
        <td align="center">
            <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background:#ffffff; border-radius:8px; overflow:hidden;">
                <tr>
                    <td align="{{ $logo ? 'center' : 'left' }}" style="background:{{ $marca['color_cabecera'] }}; color:{{ $marca['color_texto_cabecera'] }}; padding:18px 24px; font-size:20px; font-weight:bold;">
                        @if($logo)
                            <img src="{{ $logo }}" alt="{{ $academia['nombre'] }}" height="56" style="height:56px; max-width:260px; width:auto; border:0; display:inline-block;">
                        @else
                            {{ $academia['nombre'] }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td height="4" style="height:4px; line-height:4px; font-size:0; background:{{ $franja }};">&nbsp;</td>
                </tr>
                <tr>
                    <td style="padding:24px; font-size:15px; line-height:1.6;">
                        {{-- El saludo lo pone el diseño solo en los correos al alumno; los demás lo traen en su texto. --}}
                        @if(!empty($datos['alumno']))
                            <p style="margin-top:0;">Hola <strong>{{ $datos['alumno'] }}</strong>,</p>
                        @endif
                        @yield('contenido')
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px; background:#fafafa; font-size:12px; color:#777; border-top:1px solid #eee;">
                        <strong style="color:{{ $marca['color_primario'] }};">{{ $academia['nombre'] }}</strong>
                        @if(!empty($academia['telefono'])) · Tel. {{ $academia['telefono'] }} @endif
                        @if(!empty($academia['correo'])) · {{ $academia['correo'] }} @endif
                        <br>Este es un mensaje automático; si tienes dudas responde a este correo o comunícate con la academia.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
