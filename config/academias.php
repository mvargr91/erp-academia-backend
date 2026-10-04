<?php

return [
    /*
    | Dominio base del ERP. Cada academia se atiende en <codigo>.<dominio>
    | (p. ej. salsa.miacademia.com). Se usa para resolver la academia por Host
    | y para armar los enlaces de los correos.
    */
    'dominio' => env('ACADEMIAS_DOMINIO', 'erp-academia.test'),

    // Protocolo de los enlaces de los correos.
    'protocolo' => env('ACADEMIAS_PROTOCOLO', 'https'),

    // Prefijo del nombre de la BD de cada academia nueva: <prefijo><codigo>.
    'prefijo_bd' => env('ACADEMIAS_PREFIJO_BD', 'erp_acad_'),

    /*
    | Academia a usar cuando la petición no trae subdominio ni cabecera X-Academia
    | (desarrollo local: localhost, erp-academia.test). Dejar vacío en producción.
    */
    'por_defecto' => env('ACADEMIA_POR_DEFECTO'),

    // BD de la que se copian los clientes OAuth (Passport) a cada academia nueva.
    'bd_plantilla' => env('DB_DATABASE'),

    // Subdominios que nunca corresponden a una academia.
    'subdominios_reservados' => ['www', 'api', 'mail'],

    'notificaciones' => [
        'dias_recordatorio' => 5,
        'dias_entre_avisos_mora' => 7,
    ],

    /*
    | Cobro del dueño del ERP a cada academia (tarifa única, ajustable por academia).
    | La cuenta de cobro se genera el día de corte y vence 'dias_plazo' después;
    | pasados 'dias_gracia' desde el vencimiento sin pago, la academia se suspende.
    */
    'facturacion' => [
        'marca' => env('ERP_MARCA', env('APP_NAME', 'ERP Academias')),
        'tarifa_base' => (float) env('ERP_TARIFA_MENSUAL', 0),
        'dias_plazo' => (int) env('ERP_DIAS_PLAZO', 5),
        'dias_recordatorio' => 3,
        'dias_gracia' => (int) env('ERP_DIAS_GRACIA', 10),
        'dias_entre_avisos_mora' => 7,
        // Texto con los datos para pagar (cuenta bancaria, Nequi, etc.) que va en los correos.
        'datos_pago' => env('ERP_DATOS_PAGO', ''),
        'contacto' => env('ERP_CORREO_CONTACTO', env('MAIL_FROM_ADDRESS')),
    ],
];
