@extends('emails.academia.layout')

@section('contenido')
    @if(!empty($datos['alumno_nuevo']))
        <p>¡Te damos la bienvenida a <strong>{{ $academia['nombre'] }}</strong>! Este es el resumen de tu matrícula:</p>
    @else
        <p>Este es el resumen de tu nueva matrícula:</p>
    @endif

    <ul style="padding-left:18px;">
        @foreach(explode("\n", $datos['cursos'] ?? '') as $curso)
            <li>{{ $curso }}</li>
        @endforeach
    </ul>

    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin:12px 0;">
        <tr><td style="color:#777;">Total</td><td><strong>${{ number_format($datos['valor'] ?? 0, 0, ',', '.') }}</strong></td></tr>
        <tr><td style="color:#777;">Pagado</td><td><strong>{{ $datos['pagado'] ?? '$0' }}</strong>
            @if(!empty($datos['fecha_pago'])) ({{ $datos['metodo'] }}, {{ $datos['fecha_pago'] }}) @endif</td></tr>
        <tr><td style="color:#777;">Saldo pendiente</td><td><strong>${{ number_format($datos['saldo'] ?? 0, 0, ',', '.') }}</strong></td></tr>
    </table>

    <p style="font-size:13px; color:#777;">Si una clase cae en festivo o en un cierre de la academia, se corre a la semana siguiente.</p>

    <p>Trae ropa cómoda, hidratación y muchas ganas de bailar. ¡Nos vemos en clase!</p>
@endsection
