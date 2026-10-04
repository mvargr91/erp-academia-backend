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
                    <td style="background:{{ $color ?? '#1A73E8' }}; color:#ffffff; padding:20px 24px; font-size:20px; font-weight:bold;">
                        {{ $academia['nombre'] }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px; font-size:15px; line-height:1.6;">
                        <p style="margin-top:0;">Hola <strong>{{ $datos['alumno'] }}</strong>,</p>
                        @yield('contenido')
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px; background:#fafafa; font-size:12px; color:#777; border-top:1px solid #eee;">
                        {{ $academia['nombre'] }}
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
