<?php

namespace App\Http\Controllers\Central;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Central\Academia;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Support\Academias\GestorAcademias;
use Illuminate\Support\Facades\Validator;
use App\Services\Central\FacturacionAcademias;

/**
 * Panel del dueño del ERP: alta y administración de las academias clientes.
 * Solo accesible desde la academia administradora (middleware academia.administradora).
 */
class AcademiaController extends Controller
{
    private const ESTADOS_CUENTA = [
        'al_dia' => 'Al día',
        'pendiente' => 'Pago pendiente',
        'en_mora' => 'En mora',
        'suspendida' => 'Suspendida',
        'sin_cobro' => 'Sin cobro',
    ];

    private function formatear(Academia $a, bool $conResumen = false): array
    {
        $datos = [
            'id' => $a->id,
            'codigo' => $a->codigo,
            'nombre' => $a->nombre,
            'base_datos' => $a->base_datos,
            'correo' => $a->correo,
            'telefono' => $a->telefono,
            'dia_pago' => $a->dia_pago,
            'tarifa_mensual' => $a->tarifa_mensual,
            'dia_corte' => $a->dia_corte,
            'fecha_inicio_cobro' => optional($a->fecha_inicio_cobro)->format('Y-m-d'),
            'activa' => $a->activa ? 1 : 0,
            'estado' => $a->activa ? 1 : 0,
            'suspendida_por_mora' => $a->suspendida_por_mora ? 1 : 0,
            'es_administradora' => $a->es_administradora ? 1 : 0,
            'url' => $a->url(),
            'fecha_creacion' => optional($a->created_at)->format('Y-m-d H:i:s'),
            'fecha_modificacion' => optional($a->updated_at)->format('Y-m-d H:i:s'),
        ];

        if ($conResumen) {
            $cuenta = FacturacionAcademias::estadoCuenta($a);
            $datos['estado_cuenta'] = $cuenta['estado'];
            $datos['estado_cuenta_nombre'] = self::ESTADOS_CUENTA[$cuenta['estado']];
            $datos['saldo_pendiente'] = $cuenta['saldo'];
            $datos['alumnos_activos'] = $this->alumnosActivos($a);
        }
        return $datos;
    }

    /** Alumnos activos en la BD de la academia (mismo servidor MySQL). */
    private function alumnosActivos(Academia $a): ?int
    {
        try {
            return (int) DB::connection('central')
                ->selectOne("SELECT COUNT(*) AS total FROM `{$a->base_datos}`.`alumnos` WHERE estado = 1")
                ->total;
        } catch (Exception $e) {
            return null;
        }
    }

    public function index(Request $request)
    {
        try {
            if ($request->ligera) {
                return response(
                    Academia::where('es_administradora', false)->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'tarifa_mensual']),
                    Response::HTTP_OK
                );
            }

            $query = Academia::query();
            if ($request->filled('nombre')) {
                $query->where(function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->nombre . '%')
                        ->orWhere('codigo', 'like', '%' . $request->nombre . '%');
                });
            }
            $ordenables = ['codigo', 'nombre', 'dia_pago', 'tarifa_mensual', 'dia_corte', 'activa', 'created_at'];
            if ($request->filled('ordenar_por')) {
                foreach (explode(',', $request->ordenar_por) as $orden) {
                    [$campo, $dir] = array_pad(explode(':', $orden), 2, 'asc');
                    if (in_array($campo, $ordenables, true)) {
                        $query->orderBy($campo, $dir === 'desc' ? 'desc' : 'asc');
                    }
                }
            }
            $query->orderBy('nombre');

            $pagina = $query->paginate((int) ($request->limite ?? 100));
            return response([
                'datos' => $pagina->getCollection()->map(fn ($a) => $this->formatear($a, true))->all(),
                'desde' => $pagina->firstItem(),
                'hasta' => $pagina->lastItem(),
                'por_pagina' => $pagina->perPage(),
                'pagina_actual' => $pagina->currentPage(),
                'ultima_pagina' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        $academia = Academia::find($id);
        if (!$academia) {
            return response(get_response_body(['La academia no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->formatear($academia, true), Response::HTTP_OK);
    }

    private function reglasCobro(): array
    {
        return [
            'tarifa_mensual' => 'required|numeric|min:0',
            'dia_corte' => 'required|integer|between:1,28',
            'fecha_inicio_cobro' => 'nullable|date',
        ];
    }

    public function store(Request $request)
    {
        $datos = $request->all();
        $datos['codigo'] = strtolower($datos['codigo'] ?? '');
        $validator = Validator::make($datos, array_merge([
            'codigo' => ['required', 'regex:/^[a-z0-9][a-z0-9-]{1,38}$/', 'unique:central.academias,codigo'],
            'nombre' => 'required|string|max:150',
            'correo' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'dia_pago' => 'nullable|integer|between:1,28',
            'usuario_admin' => 'required|string|max:50',
            'clave_admin' => 'nullable|string|min:8',
        ], $this->reglasCobro()), [
            'codigo.regex' => 'El código solo admite minúsculas, números y guiones (será el subdominio).',
            'codigo.unique' => 'Ya existe una academia con ese código.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        // Crear la BD y correr las migraciones puede tardar más que el límite por defecto.
        set_time_limit(300);
        $clave = $datos['clave_admin'] ?? null ?: Str::password(12, symbols: false);

        try {
            $academia = GestorAcademias::crear($datos, $datos['usuario_admin'], $clave);
        } catch (Exception $e) {
            return response(get_response_body(['No se pudo crear la academia: ' . $e->getMessage()]), Response::HTTP_CONFLICT);
        }

        $respuesta = $this->formatear($academia);
        // Se devuelve una sola vez para entregarla al cliente.
        $respuesta['usuario_admin'] = $datos['usuario_admin'];
        $respuesta['clave_admin'] = $clave;
        return response(get_response_body(['La academia ha sido creada.', 2], $respuesta), Response::HTTP_CREATED);
    }

    public function update(Request $request, $id)
    {
        $academia = Academia::find($id);
        if (!$academia) {
            return response(get_response_body(['La academia no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), array_merge([
            'nombre' => 'required|string|max:150',
            'correo' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'dia_pago' => 'nullable|integer|between:1,28',
            'activa' => 'required|boolean',
        ], $this->reglasCobro()));
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ($academia->es_administradora && !$request->boolean('activa')) {
            return response(get_response_body(['La academia administradora no se puede suspender.']), Response::HTTP_CONFLICT);
        }

        $datos = $request->only(['nombre', 'correo', 'telefono', 'dia_pago', 'activa', 'tarifa_mensual', 'dia_corte', 'fecha_inicio_cobro']);
        // Cambiar el estado a mano quita la marca de suspensión automática: activar = perdonar la mora,
        // suspender = pausa manual (deja de facturarse).
        if ($request->boolean('activa') !== $academia->activa) {
            $datos['suspendida_por_mora'] = false;
        }
        $academia->update($datos);
        return response(get_response_body(['La academia ha sido modificada.', 1], $this->formatear($academia, true)), Response::HTTP_OK);
    }
}
