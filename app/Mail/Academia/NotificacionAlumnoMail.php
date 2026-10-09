<?php

namespace App\Mail\Academia;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\Academias\Apariencia;
use App\Support\Configuracion\Configuracion;

/**
 * Correo al alumno: bienvenida (a la academia y al curso), recordatorio de pago, mora y avisos de paquete.
 *
 * El asunto y el cuerpo salen de la plantilla de correo de la academia (Configuración →
 * Plantillas de correo). La plantilla se arma al construir el correo, en el contexto de la
 * academia, porque el envío puede ir por la cola (sin academia activa). Si la plantilla no
 * existe o está inactiva, se usa la vista Blade por defecto.
 */
class NotificacionAlumnoMail extends Mailable
{
    use Queueable, SerializesModels;

    public const ALUMNO_NUEVO = 'alumno_nuevo';
    public const BIENVENIDA = 'bienvenida';
    public const RECORDATORIO = 'recordatorio';
    public const MORA = 'mora';
    public const PAQUETE_VENCE = 'paquete_vence';
    public const PAQUETE_ULTIMA = 'paquete_ultima';
    public const CLASE_PRIVADA = 'clase_privada';
    public const PAGO = 'pago';
    public const CUMPLEANOS = 'cumpleanos';
    public const MATRICULA = 'matricula';

    private const PLANTILLAS = [
        self::ALUMNO_NUEVO => 'ALUMNO_BIENVENIDA',
        self::BIENVENIDA => 'BIENVENIDA',
        self::RECORDATORIO => 'RECORDATORIO_PAGO',
        self::MORA => 'MORA',
        self::PAQUETE_VENCE => 'PAQUETE_VENCE',
        self::PAQUETE_ULTIMA => 'PAQUETE_ULTIMA',
        self::CLASE_PRIVADA => 'CLASE_PRIVADA_RECORDATORIO',
        self::PAGO => 'PAGO_CONFIRMACION',
        self::CUMPLEANOS => 'CUMPLEANOS',
        self::MATRICULA => 'MATRICULA_RESUMEN',
    ];

    private const ASUNTOS = [
        self::PAQUETE_VENCE => 'Tu paquete de clases vence el :fecha',
        self::PAQUETE_ULTIMA => 'Te queda 1 clase en tu paquete',
        self::ALUMNO_NUEVO => '¡Bienvenido(a) a :academia!',
        self::BIENVENIDA => '¡Bienvenido(a) a :curso!',
        self::RECORDATORIO => 'Recordatorio: tu próximo ciclo de :curso empieza el :fecha',
        self::MORA => 'Tienes un saldo pendiente en :curso',
        self::CLASE_PRIVADA => 'Recordatorio: tu clase personalizada es mañana',
        self::PAGO => 'Recibimos tu pago',
        self::CUMPLEANOS => '¡Feliz cumpleaños!',
        self::MATRICULA => 'Tu matrícula en :academia',
    ];

    /** Plantilla ya armada (asunto y cuerpo HTML), o null para usar la vista por defecto. */
    public ?array $plantilla;

    /** Colores y logo de la academia (Apariencia), tomados al construir el correo igual que la plantilla. */
    public array $marca;

    /**
     * @param string $tipo  bienvenida | recordatorio | mora | paquete_vence | paquete_ultima
     * @param array $academia  nombre, correo, telefono, url
     * @param array $datos  alumno, curso, valor, saldo, fecha_vencimiento, ...
     */
    public function __construct(public string $tipo, public array $academia, public array $datos)
    {
        $this->plantilla = Configuracion::plantilla(self::PLANTILLAS[$tipo], self::variables($academia, $datos));
        $this->marca = Apariencia::paraCorreo();
    }

    /** Variables disponibles en las plantillas (montos ya formateados). */
    public static function variables(array $academia, array $datos): array
    {
        $dinero = fn ($v) => $v === null || $v === '' ? '' : '$' . number_format((float) $v, 0, ',', '.');
        return array_merge($datos, [
            'academia' => $academia['nombre'] ?? '',
            'valor' => $dinero($datos['valor'] ?? null),
            'saldo' => $dinero($datos['saldo'] ?? null),
            'paquete' => $datos['curso'] ?? '',
        ]);
    }

    public function build()
    {
        if ($this->plantilla) {
            $correo = $this->from(config('mail.from.address'), $this->academia['nombre'])
                ->subject($this->plantilla['asunto'])
                ->view('emails.academia.plantilla', ['cuerpo' => $this->plantilla['html']]);
        } else {
            $asunto = strtr(self::ASUNTOS[$this->tipo], [
                ':academia' => $this->academia['nombre'] ?? '',
                ':curso' => $this->datos['curso'] ?? '',
                ':fecha' => $this->datos['fecha_vencimiento'] ?? '',
            ]);
            $correo = $this->from(config('mail.from.address'), $this->academia['nombre'])
                ->subject($asunto)
                ->view('emails.academia.' . $this->tipo);
        }

        if (!empty($this->academia['correo'])) {
            $correo->replyTo($this->academia['correo'], $this->academia['nombre']);
        }
        return $correo;
    }
}
