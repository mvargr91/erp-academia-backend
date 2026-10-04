@extends('emails.academia.layout', ['color' => '#C62828'])

@section('contenido')
    <p>Según nuestros registros, tienes un saldo pendiente en el curso <strong>{{ $datos['curso'] }}</strong>.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin:12px 0;">
        <tr><td style="color:#777;">Saldo en mora</td><td><strong style="color:#c62828;">${{ number_format($datos['saldo'], 0, ',', '.') }}</strong></td></tr>
        <tr><td style="color:#777;">Venció el</td><td><strong>{{ $datos['fecha_vencimiento'] }}</strong></td></tr>
    </table>

    <p>Te invitamos a ponerte al día para seguir disfrutando de tus clases. Si ya pagaste, comunícate con la academia
        para actualizar tu estado de cuenta.</p>
@endsection
