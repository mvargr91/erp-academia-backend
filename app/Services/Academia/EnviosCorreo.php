<?php

namespace App\Services\Academia;

use Carbon\Carbon;
use App\Models\Central\Academia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Jobs\EnviarCorreoMasivo;
use App\Support\Academias\GestorAcademias;
use App\Services\Central\FacturacionAcademias;

/**
 * Correos manuales/masivos de la academia: calcula los destinatarios de una audiencia y crea el
 * envío con un trabajo en cola por destinatario (cada correo va personalizado).
 */
class EnviosCorreo
{
    public const AUDIENCIAS = [
        'todos' => 'Todos los alumnos activos',
        'curso' => 'Alumnos de un curso',
        'mora' => 'Alumnos con saldo pendiente',
        'seleccion' => 'Alumnos seleccionados',
        'academias' => 'Mis academias clientes',
    ];

    /** Variables de las plantillas manuales. */
    public const VARIABLES = [
        'alumno' => 'Nombre del destinatario',
        'academia' => 'Nombre de la academia',
        'saldo' => 'Saldo pendiente del destinatario',
        'curso' => 'Curso (si el envío es a un curso)',
    ];

    /** Límite diario aproximado de Gmail: se advierte antes de enviar más. */
    public const LIMITE_DIARIO = 450;

    private static function dinero(float $v): string
    {
        return '$' . number_format($v, 0, ',', '.');
    }

    /**
     * Destinatarios de una audiencia: [alumno_id, nombre, correo, variables].
     * @return Collection<int, array>
     */
    public static function destinatarios(string $audiencia, array $filtro = []): Collection
    {
        $academia = GestorAcademias::actual();
        $nombreAcademia = $academia?->nombre ?? config('app.name');

        if ($audiencia === 'academias') {
            return Academia::where('es_administradora', false)->where('activa', true)->orderBy('nombre')->get()
                ->map(fn ($a) => [
                    'alumno_id' => null,
                    'nombre' => $a->nombre,
                    'correo' => $a->correo,
                    'variables' => [
                        'alumno' => $a->nombre,
                        'academia' => $a->nombre,
                        'saldo' => self::dinero(FacturacionAcademias::estadoCuenta($a)['saldo'] ?? 0),
                        'curso' => '',
                    ],
                ]);
        }

        // Saldo pendiente de cada alumno sumando sus matrículas.
        $saldos = DB::table('curso_alumno')->where('estado', 1)->groupBy('alumno_id')
            ->select('alumno_id', DB::raw('SUM(saldo) as saldo'))->pluck('saldo', 'alumno_id');

        $query = DB::table('alumnos as a')->where('a.estado', 1);
        $curso = null;
        switch ($audiencia) {
            case 'curso':
                $curso = DB::table('cursos as c')->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
                    ->where('c.id', $filtro['curso_id'] ?? 0)->select(DB::raw('COALESCE(c.nombre, r.nombre) as nombre'))->value('nombre');
                $query->whereIn('a.id', DB::table('curso_alumno')->where('curso_id', $filtro['curso_id'] ?? 0)->where('estado', 1)->select('alumno_id'));
                break;
            case 'mora':
                $query->whereIn('a.id', $saldos->filter(fn ($s) => (float) $s > 0)->keys()->all());
                break;
            case 'seleccion':
                $query->whereIn('a.id', array_map('intval', $filtro['alumnos'] ?? []));
                break;
        }

        return $query->orderBy('a.nombres')->get()->map(fn ($a) => [
            'alumno_id' => $a->id,
            'nombre' => trim("{$a->nombres} {$a->apellidos}"),
            'correo' => $a->correo,
            'variables' => [
                'alumno' => trim("{$a->nombres} {$a->apellidos}"),
                'academia' => $nombreAcademia,
                'saldo' => self::dinero((float) ($saldos[$a->id] ?? 0)),
                'curso' => $curso ?? '',
            ],
        ]);
    }

    /** Resumen previo: cuántos reciben y quiénes no tienen correo. */
    public static function resumen(string $audiencia, array $filtro = []): array
    {
        $todos = self::destinatarios($audiencia, $filtro);
        $sinCorreo = $todos->filter(fn ($d) => empty($d['correo']));
        $conCorreo = $todos->count() - $sinCorreo->count();
        return [
            'total' => $todos->count(),
            'con_correo' => $conCorreo,
            'sin_correo' => $sinCorreo->pluck('nombre')->values(),
            'supera_limite' => $conCorreo > self::LIMITE_DIARIO,
            'limite' => self::LIMITE_DIARIO,
        ];
    }

    /** Crea el envío y encola un correo por destinatario con correo. Devuelve el id del envío. */
    public static function enviar(array $datos, object $usuario): int
    {
        $academia = GestorAcademias::actual();
        $destinatarios = self::destinatarios($datos['audiencia'], $datos['filtro'] ?? [])
            ->filter(fn ($d) => !empty($d['correo']))->values();

        $ahora = Carbon::now();
        $envioId = DB::transaction(function () use ($datos, $usuario, $destinatarios, $ahora) {
            $envioId = DB::table('envios_correo')->insertGetId([
                'plantilla_id' => $datos['plantilla_id'] ?? null,
                'asunto' => $datos['asunto'],
                'texto' => $datos['texto'],
                'audiencia' => $datos['audiencia'],
                'filtro' => json_encode($datos['filtro'] ?? []),
                'total' => $destinatarios->count(),
                'estado' => $destinatarios->isEmpty() ? 'completado' : 'enviando',
                'usuario_creacion_id' => $usuario->id,
                'usuario_creacion_nombre' => $usuario->nombre,
                'usuario_modificacion_id' => $usuario->id,
                'usuario_modificacion_nombre' => $usuario->nombre,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            foreach ($destinatarios as $d) {
                DB::table('envio_correo_destinatarios')->insert([
                    'envio_id' => $envioId,
                    'alumno_id' => $d['alumno_id'],
                    'nombre' => $d['nombre'],
                    'correo' => $d['correo'],
                    'variables' => json_encode($d['variables'], JSON_UNESCAPED_UNICODE),
                    'estado' => 'pendiente',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            return $envioId;
        });

        $ids = DB::table('envio_correo_destinatarios')->where('envio_id', $envioId)->pluck('id');
        foreach ($ids as $id) {
            EnviarCorreoMasivo::dispatch($academia->codigo, (int) $id);
        }
        return $envioId;
    }
}
