@extends('emails.academia.layout', ['color' => '#F9A825'])

@section('contenido')
    <p>Te recordamos que tu próximo ciclo de <strong>{{ $datos['curso'] }}</strong>
        @if(!empty($datos['clases_por_ciclo'])) ({{ $datos['clases_por_ciclo'] }} clases) @endif
        empieza el <strong>{{ $datos['fecha_vencimiento'] }}</strong>; ese día vence su pago.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin:12px 0;">
        <tr><td style="color:#777;">Valor del ciclo</td><td><strong>${{ number_format($datos['valor'], 0, ',', '.') }}</strong></td></tr>
        @if(($datos['saldo'] ?? 0) > 0)
            <tr><td style="color:#777;">Saldo pendiente a hoy</td><td><strong style="color:#c62828;">${{ number_format($datos['saldo'], 0, ',', '.') }}</strong></td></tr>
        @endif
    </table>

    <p>Si ya realizaste el pago, puedes ignorar este mensaje. ¡Gracias por bailar con nosotros!</p>
@endsection
