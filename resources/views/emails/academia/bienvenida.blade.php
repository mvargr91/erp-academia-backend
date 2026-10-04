@extends('emails.academia.layout', ['color' => '#1A73E8'])

@section('contenido')
    <p>¡Te damos la bienvenida a <strong>{{ $datos['curso'] }}</strong>! Nos alegra que hagas parte de nuestra academia.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin:12px 0;">
        @if(!empty($datos['horario']))
            <tr><td style="color:#777;">Horario</td><td><strong>{{ $datos['horario'] }}</strong></td></tr>
        @endif
        @if(!empty($datos['profesor']))
            <tr><td style="color:#777;">Profesor(a)</td><td><strong>{{ $datos['profesor'] }}</strong></td></tr>
        @endif
        @if(!empty($datos['primera_clase']))
            <tr><td style="color:#777;">Tu primera clase</td><td><strong>{{ $datos['primera_clase'] }}</strong></td></tr>
        @endif
        @if(!empty($datos['valor']))
            <tr><td style="color:#777;">Valor por ciclo</td><td><strong>${{ number_format($datos['valor'], 0, ',', '.') }}</strong>
                ({{ $datos['clases_por_ciclo'] }} clases)</td></tr>
        @endif
        @if(!empty($datos['proximo_pago']))
            <tr><td style="color:#777;">Próximo pago</td><td><strong>{{ $datos['proximo_pago'] }}</strong></td></tr>
        @endif
    </table>

    @if(!empty($datos['clases_por_ciclo']))
        <p style="font-size:13px; color:#777;">Cada pago cubre {{ $datos['clases_por_ciclo'] }} clases. Si una clase cae en festivo
            o en un cierre de la academia, se corre a la semana siguiente y tu próximo pago también.</p>
    @endif

    <p>Trae ropa cómoda, hidratación y muchas ganas de bailar. ¡Nos vemos en clase!</p>
@endsection
