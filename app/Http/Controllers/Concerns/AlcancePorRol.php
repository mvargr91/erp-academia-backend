<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe los datos según el rol del usuario autenticado:
 *  - Proveedor: solo registros de su proveedor turístico (proveedores_turisticos.usuario_id).
 *  - Cliente: solo registros propios (usuario_id).
 *  - Cualquier otro rol (administración): sin restricción.
 */
trait AlcancePorRol
{
    public const ROL_PROVEEDOR = 'Proveedor';
    public const ROL_CLIENTE = 'Cliente';

    protected function rolActual()
    {
        $rol = Auth::user()?->rol();
        return $rol->name ?? null;
    }

    protected function esProveedor()
    {
        return $this->rolActual() === self::ROL_PROVEEDOR;
    }

    protected function esCliente()
    {
        return $this->rolActual() === self::ROL_CLIENTE;
    }

    protected function usuarioActualId()
    {
        $usuario = Auth::user()?->usuario();
        return $usuario->id ?? null;
    }

    // Un usuario con rol Proveedor sin proveedor asociado no ve nada (id 0).
    protected function proveedorActualId()
    {
        return DB::table('proveedores_turisticos')
            ->where('usuario_id', $this->usuarioActualId())
            ->value('id') ?? 0;
    }

    /**
     * Fuerza en el DTO los filtros del rol (sobrescribe lo que envíe el cliente HTTP).
     */
    protected function aplicarAlcance(array $datos, $campoProveedor = 'proveedor_id', $campoUsuario = 'usuario_id')
    {
        if ($this->esProveedor() && $campoProveedor) {
            $datos[$campoProveedor] = $this->proveedorActualId();
        }
        if ($this->esCliente() && $campoUsuario) {
            $datos[$campoUsuario] = $this->usuarioActualId();
        }
        return $datos;
    }

    /**
     * ¿El registro pertenece al alcance del usuario? Recibe el proveedor y/o usuario dueños.
     */
    protected function enAlcance($proveedorId = null, $usuarioId = null)
    {
        if ($this->esProveedor()) {
            return $proveedorId !== null && (int) $proveedorId === (int) $this->proveedorActualId();
        }
        if ($this->esCliente()) {
            return $usuarioId !== null && (int) $usuarioId === (int) $this->usuarioActualId();
        }
        return true;
    }

    protected function experienciaEnAlcance($experienciaId)
    {
        if (!$this->esProveedor() && !$this->esCliente()) {
            return true;
        }
        $proveedorId = DB::table('experiencias')->where('id', $experienciaId)->value('proveedor_id');
        return $this->esProveedor() && $this->enAlcance($proveedorId);
    }

    protected function reservaEnAlcance($reservaId)
    {
        $reserva = DB::table('reservas')->where('id', $reservaId)->select('proveedor_id', 'usuario_id')->first();
        return $reserva && $this->enAlcance($reserva->proveedor_id, $reserva->usuario_id);
    }

    // Para recursos hijos de experiencia: busca la experiencia del registro y valida.
    protected function registroDeExperienciaEnAlcance($tabla, $id)
    {
        $experienciaId = DB::table($tabla)->where('id', $id)->value('experiencia_id');
        return $experienciaId && $this->experienciaEnAlcance($experienciaId);
    }

    protected function respuestaSinAcceso()
    {
        return response(
            get_response_body(['No tiene acceso a este registro.']),
            Response::HTTP_FORBIDDEN
        );
    }
}
