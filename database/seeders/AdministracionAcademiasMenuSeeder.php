<?php

namespace Database\Seeders;

/**
 * Menú "Administración ERP" (gestión de academias). Solo se ejecuta en la BD
 * de la academia administradora (la del dueño del ERP):
 *   php artisan db:seed --class=AdministracionAcademiasMenuSeeder
 * Es idempotente.
 */
class AdministracionAcademiasMenuSeeder extends AcademiaMenuSeeder
{
    protected function menu(): array
    {
        return [
            'Administración ERP' => ['admin_panel_settings', 7, [
                ['Panel ERP', '/panel-erp', 'insights', 'PanelErp'],
                ['Academias', '/academias', 'apartment', 'Academia', ['Exportar' => 'Listar']],
                ['Cuentas de cobro', '/facturas-academias', 'receipt_long', 'FacturaAcademia', ['Exportar' => 'Listar']],
                ['Pagos recibidos', '/pagos-academias', 'account_balance', 'PagoAcademia', ['Exportar' => 'Listar']],
                ['Festivos', '/festivos', 'celebration', 'Festivo', ['Exportar' => 'Listar']],
            ]],
        ];
    }
}
