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
                        {{ $datos['marca'] }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px; font-size:15px; line-height:1.6;">
                        <p style="margin-top:0;">Hola, equipo de <strong>{{ $datos['academia'] }}</strong>:</p>
                        @yield('contenido')

                        @hasSection('conDatosPago')
                            @if(!empty($datos['datos_pago']))
                                <div style="margin-top:16px; padding:12px 16px; background:#f5f8ff; border-left:4px solid #1A73E8;">
                                    <strong>Datos para el pago</strong><br>
                                    {!! nl2br(e($datos['datos_pago'])) !!}
                                    <br><span style="color:#777; font-size:13px;">Indica en la referencia la cuenta de cobro {{ $datos['numero'] }}.</span>
                                </div>
                            @endif
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px; background:#fafafa; font-size:12px; color:#777; border-top:1px solid #eee;">
                        {{ $datos['marca'] }}
                        @if(!empty($datos['contacto'])) · {{ $datos['contacto'] }} @endif
                        <br>Si ya realizaste el pago, responde este correo con el soporte.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
