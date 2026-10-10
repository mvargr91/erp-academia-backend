<?php

namespace App\Support\Configuracion;

/**
 * Catálogo de parámetros y plantillas de correo que el sistema conoce y consume.
 * Las academias solo editan valores/textos; los códigos nacen aquí (ConfiguracionAcademiaSeeder
 * los crea en cada academia sin sobrescribir lo que ya editaron).
 * Las entradas con 'admin' => true solo existen en la academia administradora (cobro del ERP).
 */
class CatalogoConfiguracion
{
    /** codigo => [descripcion, tipo, valor por defecto, grupo, admin?] */
    public static function parametros(): array
    {
        return [
            // Cobro a alumnos
            'CLASES_POR_CICLO' => ['Clases que tiene cada ciclo de pago de un curso', 'numero', '4', 'Cobro a alumnos'],
            'DIAS_RECORDATORIO_PAGO' => ['Días antes del próximo ciclo para enviar el recordatorio de pago', 'numero', '5', 'Cobro a alumnos'],
            'DIAS_ENTRE_AVISOS_MORA' => ['Cada cuántos días se repite el aviso de mora', 'numero', '7', 'Cobro a alumnos'],
            // Clases personalizadas y paquetes
            'HORAS_CANCELACION_CLASE' => ['Horas mínimas de anticipación para cancelar una clase privada sin que descuente', 'numero', '24', 'Clases personalizadas'],
            'DIAS_AVISO_VENCE_PAQUETE' => ['Días antes del vencimiento de un paquete para avisar al alumno', 'numero', '5', 'Paquetes'],
            // Cobro del ERP a las academias (solo academia administradora)
            'ERP_TARIFA_MENSUAL' => ['Tarifa mensual sugerida al crear una academia', 'numero', (string) config('academias.facturacion.tarifa_base'), 'Cobro del ERP', true],
            'ERP_DIAS_PLAZO' => ['Días entre la cuenta de cobro y su vencimiento', 'numero', (string) config('academias.facturacion.dias_plazo'), 'Cobro del ERP', true],
            'ERP_DIAS_RECORDATORIO' => ['Días antes del vencimiento para recordar la cuenta de cobro', 'numero', (string) config('academias.facturacion.dias_recordatorio'), 'Cobro del ERP', true],
            'ERP_DIAS_GRACIA' => ['Días de mora antes de suspender la academia', 'numero', (string) config('academias.facturacion.dias_gracia'), 'Cobro del ERP', true],
            'ERP_DIAS_ENTRE_AVISOS_MORA' => ['Cada cuántos días se repite el aviso de mora a la academia', 'numero', (string) config('academias.facturacion.dias_entre_avisos_mora'), 'Cobro del ERP', true],
            'ERP_DATOS_PAGO' => ['Datos para pagar (cuenta bancaria, Nequi…): van en los correos y en el aviso del ERP', 'texto', (string) config('academias.facturacion.datos_pago'), 'Cobro del ERP', true],
            'ERP_CORREO_CONTACTO' => ['Correo de contacto para las academias (responder a)', 'texto', (string) config('academias.facturacion.contacto'), 'Cobro del ERP', true],
        ];
    }

    /** codigo => [nombre, asunto, cuerpo HTML, variables {var: descripción}, admin?] */
    public static function plantillas(): array
    {
        $academia = ['academia' => 'Nombre de la academia'];
        $alumno = ['alumno' => 'Nombre del alumno'];

        return [
            'ALUMNO_BIENVENIDA' => [
                'Bienvenida a la academia (alumno nuevo)',
                '¡Bienvenido(a) a {academia}!',
                '<p>¡Te damos la bienvenida a <strong>{academia}</strong>! Ya quedaste registrado(a) como alumno(a).</p>'
                . '<p>Cuando te matricules en un curso te enviaremos el horario, tu primera clase y los datos de pago.</p>'
                . '<p>Si tienes alguna pregunta, responde a este correo. ¡Nos vemos en clase!</p>',
                $alumno + $academia,
            ],
            'BIENVENIDA' => [
                'Bienvenida al curso',
                '¡Bienvenido(a) a {curso}!',
                '<p>¡Te damos la bienvenida a <strong>{curso}</strong>! Nos alegra que hagas parte de nuestra academia.</p>'
                . '<ul><li>Horario: <strong>{horario}</strong></li><li>Profesor(a): <strong>{profesor}</strong></li>'
                . '<li>Tu primera clase: <strong>{primera_clase}</strong></li><li>Valor por ciclo: <strong>{valor}</strong> ({clases_por_ciclo} clases)</li>'
                . '<li>Próximo pago: <strong>{proximo_pago}</strong></li></ul>'
                . '<p>Si una clase cae en festivo o en un cierre de la academia, se corre a la semana siguiente y tu próximo pago también.</p>'
                . '<p>Trae ropa cómoda, hidratación y muchas ganas de bailar. ¡Nos vemos en clase!</p>',
                $alumno + ['curso' => 'Nombre del curso', 'horario' => 'Día y hora', 'profesor' => 'Profesor(a)',
                    'primera_clase' => 'Fecha de la primera clase', 'valor' => 'Valor del ciclo', 'clases_por_ciclo' => 'Clases que cubre el pago',
                    'proximo_pago' => 'Fecha del próximo pago'] + $academia,
            ],
            'RECORDATORIO_PAGO' => [
                'Recordatorio de pago del ciclo',
                'Recordatorio: tu próximo ciclo de {curso} empieza el {fecha_vencimiento}',
                '<p>Te recordamos que tu próximo ciclo de <strong>{curso}</strong> ({clases_por_ciclo} clases) empieza el '
                . '<strong>{fecha_vencimiento}</strong>; ese día vence su pago.</p>'
                . '<ul><li>Valor del ciclo: <strong>{valor}</strong></li><li>Saldo pendiente a hoy: <strong>{saldo}</strong></li></ul>'
                . '<p>Si ya realizaste el pago, puedes ignorar este mensaje. ¡Gracias por bailar con nosotros!</p>',
                $alumno + ['curso' => 'Nombre del curso', 'valor' => 'Valor del ciclo', 'saldo' => 'Saldo pendiente',
                    'fecha_vencimiento' => 'Fecha de inicio del ciclo (vence el pago)', 'clases_por_ciclo' => 'Clases del ciclo'] + $academia,
            ],
            'MORA' => [
                'Aviso de mora',
                'Tienes un saldo pendiente en {curso}',
                '<p>Según nuestros registros, tienes un saldo pendiente en el curso <strong>{curso}</strong>.</p>'
                . '<ul><li>Saldo en mora: <strong>{saldo}</strong></li><li>Venció el: <strong>{fecha_vencimiento}</strong></li></ul>'
                . '<p>Te invitamos a ponerte al día para seguir disfrutando de tus clases. Si ya pagaste, comunícate con la academia.</p>',
                $alumno + ['curso' => 'Nombre del curso', 'saldo' => 'Saldo en mora', 'fecha_vencimiento' => 'Fecha en que venció'] + $academia,
            ],
            'PAQUETE_VENCE' => [
                'Paquete por vencer',
                'Tu paquete de clases vence el {fecha_vencimiento}',
                '<p>Tu paquete <strong>{paquete}</strong> vence el <strong>{fecha_vencimiento}</strong> y todavía tienes '
                . '<strong>{restantes} de {clases_total} clases</strong>.</p><p>¡Agéndalas antes de esa fecha para no perderlas!</p>',
                $alumno + ['paquete' => 'Nombre del paquete', 'restantes' => 'Clases que le quedan', 'clases_total' => 'Clases del paquete',
                    'fecha_vencimiento' => 'Fecha de vencimiento'] + $academia,
            ],
            'PAQUETE_ULTIMA' => [
                'Última clase del paquete',
                'Te queda 1 clase en tu paquete',
                '<p>Te queda <strong>1 clase</strong> de tu paquete <strong>{paquete}</strong>.</p>'
                . '<p>Si quieres seguir bailando sin interrupciones, pregúntanos por la renovación de tu paquete.</p>',
                $alumno + ['paquete' => 'Nombre del paquete', 'fecha_vencimiento' => 'Fecha de vencimiento'] + $academia,
            ],
            'CLASE_PRIVADA_RECORDATORIO' => [
                'Recordatorio de clase personalizada',
                'Recordatorio: tu clase es mañana {fecha} a las {hora}',
                '<p>Te recordamos tu clase personalizada:</p>'
                . '<ul><li>Fecha: <strong>{fecha}</strong></li><li>Hora: <strong>{hora}</strong> ({duracion} minutos)</li>'
                . '<li>Profesor(a): <strong>{profesor}</strong></li></ul>'
                . '<p>Si no puedes asistir, avísanos con al menos {horas_cancelacion} horas de anticipación para que no se descuente de tu paquete.</p>',
                $alumno + ['fecha' => 'Fecha de la clase', 'hora' => 'Hora de la clase', 'duracion' => 'Duración en minutos',
                    'profesor' => 'Profesor(a)', 'horas_cancelacion' => 'Horas mínimas para cancelar'] + $academia,
            ],
            'PAGO_CONFIRMACION' => [
                'Confirmación de pago',
                'Recibimos tu pago de {valor}',
                '<p>¡Gracias! Recibimos tu pago:</p>'
                . '<ul><li>Valor: <strong>{valor}</strong></li><li>Concepto: <strong>{concepto}</strong></li>'
                . '<li>Fecha: <strong>{fecha_pago}</strong></li><li>Medio de pago: {metodo}</li></ul>'
                . '<p>Saldo pendiente: <strong>{saldo}</strong>.</p>',
                $alumno + ['valor' => 'Valor pagado', 'concepto' => 'Curso o paquete pagado', 'fecha_pago' => 'Fecha del pago',
                    'metodo' => 'Medio de pago', 'saldo' => 'Saldo que queda'] + $academia,
            ],
            'MATRICULA_RESUMEN' => [
                'Resumen de matrícula (matrícula rápida)',
                'Tu matrícula en {academia}',
                '<p>¡Te damos la bienvenida a <strong>{academia}</strong>! Este es el resumen de tu matrícula:</p>'
                . '<p style="white-space:pre-line">{cursos}</p>'
                . '<ul><li>Total: <strong>{valor}</strong></li><li>Pagado: <strong>{pagado}</strong></li>'
                . '<li>Saldo pendiente: <strong>{saldo}</strong></li></ul>'
                . '<p>Si una clase cae en festivo o en un cierre de la academia, se corre a la semana siguiente.</p>'
                . '<p>Trae ropa cómoda, hidratación y muchas ganas de bailar. ¡Nos vemos en clase!</p>',
                $alumno + ['cursos' => 'Cursos matriculados (uno por línea: horario, primera clase y valor)', 'valor' => 'Total de la matrícula',
                    'pagado' => 'Valor pagado al matricularse', 'saldo' => 'Saldo que queda', 'fecha_pago' => 'Fecha del pago',
                    'metodo' => 'Medio de pago'] + $academia,
            ],
            'CUMPLEANOS' => [
                'Feliz cumpleaños',
                '¡Feliz cumpleaños, {alumno}! 🎉',
                '<p>Todo el equipo de <strong>{academia}</strong> te desea un muy feliz cumpleaños.</p>'
                . '<p>¡Que lo celebres bailando! Te esperamos en clase.</p>',
                $alumno + $academia,
            ],
            'RECUPERAR_CLAVE' => [
                'Recuperación de contraseña',
                'Restablece tu contraseña de {academia}',
                '<p>Hola <strong>{nombre}</strong>,</p><p>Recibimos una solicitud para restablecer tu contraseña. '
                . 'Haz clic en el siguiente enlace (válido por 60 minutos):</p><p><a href="{enlace}">Restablecer mi contraseña</a></p>'
                . '<p>Si no fuiste tú, ignora este mensaje: tu contraseña no cambiará.</p>',
                ['nombre' => 'Nombre del usuario', 'enlace' => 'Enlace para restablecer'] + $academia,
            ],

            // Cobro del ERP a las academias (academia administradora)
            'ERP_CUENTA_COBRO' => [
                'ERP · Cuenta de cobro', 'Cuenta de cobro {numero} - {marca} ({periodo})',
                '<p>Te compartimos la cuenta de cobro de tu suscripción al ERP.</p>'
                . '<ul><li>Cuenta de cobro: <strong>{numero}</strong></li><li>Periodo: <strong>{periodo}</strong></li>'
                . '<li>Valor: <strong>{valor}</strong></li><li>Vence el: <strong>{vencimiento}</strong></li></ul>',
                self::variablesErp(), true,
            ],
            'ERP_RECORDATORIO' => [
                'ERP · Recordatorio de cuenta de cobro', 'Recordatorio: tu cuenta de cobro {numero} vence el {vencimiento}',
                '<p>Te recordamos que tu cuenta de cobro <strong>{numero}</strong> por <strong>{valor}</strong> vence el '
                . '<strong>{vencimiento}</strong>.</p>',
                self::variablesErp(), true,
            ],
            'ERP_MORA' => [
                'ERP · Mora', 'Tienes un saldo vencido con {marca}',
                '<p>Tu cuenta de cobro <strong>{numero}</strong> venció el <strong>{vencimiento}</strong> y tienes un saldo pendiente de '
                . '<strong>{saldo_total}</strong>.</p><p>Para evitar la suspensión del servicio, realiza el pago antes del '
                . '<strong>{fecha_suspension}</strong>.</p>',
                self::variablesErp(), true,
            ],
            'ERP_SUSPENSION' => [
                'ERP · Suspensión por falta de pago', 'Servicio suspendido por falta de pago - {marca}',
                '<p>Como la cuenta de cobro <strong>{numero}</strong> lleva más de {dias_gracia} días vencida, el acceso al ERP de '
                . '<strong>{academia}</strong> fue <strong>suspendido</strong>.</p><p>Tu información está segura. En cuanto registremos el '
                . 'pago de <strong>{saldo_total}</strong>, el acceso se reactiva automáticamente.</p>',
                self::variablesErp(), true,
            ],
            'ERP_PAGO' => [
                'ERP · Pago recibido', 'Pago recibido - {marca}',
                '<p>Recibimos tu pago de <strong>{pago_valor}</strong> del {pago_fecha}, aplicado a la cuenta de cobro '
                . '<strong>{numero}</strong>. ¡Gracias!</p><p>Saldo pendiente total: <strong>{saldo_total}</strong>.</p>',
                self::variablesErp(), true,
            ],
        ];
    }

    private static function variablesErp(): array
    {
        return [
            'academia' => 'Academia cliente', 'marca' => 'Nombre del ERP', 'numero' => 'N.° de cuenta de cobro',
            'periodo' => 'Periodo', 'valor' => 'Valor', 'saldo' => 'Saldo de la cuenta', 'saldo_total' => 'Saldo total pendiente',
            'vencimiento' => 'Fecha de vencimiento', 'fecha_suspension' => 'Fecha de suspensión', 'dias_gracia' => 'Días de gracia',
            'pago_valor' => 'Valor pagado', 'pago_fecha' => 'Fecha del pago',
        ];
    }
}
