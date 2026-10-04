<table cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin:12px 0;">
    <tr><td style="color:#777;">Cuenta de cobro</td><td><strong>{{ $datos['numero'] }}</strong></td></tr>
    <tr><td style="color:#777;">Periodo</td><td><strong>{{ ucfirst($datos['periodo']) }}</strong></td></tr>
    <tr><td style="color:#777;">Valor</td><td><strong>${{ number_format($datos['valor'], 0, ',', '.') }}</strong></td></tr>
    @if(($datos['saldo'] ?? 0) > 0 && $datos['saldo'] < $datos['valor'])
        <tr><td style="color:#777;">Saldo</td><td><strong>${{ number_format($datos['saldo'], 0, ',', '.') }}</strong></td></tr>
    @endif
    <tr><td style="color:#777;">Vence el</td><td><strong>{{ $datos['vencimiento'] }}</strong></td></tr>
</table>
