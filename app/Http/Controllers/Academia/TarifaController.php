<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Plan;
use App\Services\Academia\Tarifas;
use Illuminate\Support\Facades\Validator;

/**
 * Tarifas de la academia en una sola pantalla:
 *  - Cursos: por cada modalidad, total por ciclo según cuántos cursos, individual y en pareja (única fuente del precio).
 *  - Clases personalizadas: la clase suelta y los paquetes (4, 8… clases) con su precio normal, el
 *    precio para quien ya es alumno de un curso y los días de vigencia.
 */
class TarifaController extends Controller
{
    private const NOMBRES = [Tarifas::INDIVIDUAL => 'individual', Tarifas::PAREJA => 'de pareja'];

    private function datos(): array
    {
        $personalizadas = DB::table('planes')
            ->where('periodicidad', 'paquete')
            ->where('estado', 1)
            ->orderBy('num_clases')
            ->orderBy('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'clases' => (int) $p->num_clases,
                'valor' => (float) $p->valor,
                'valor_alumno' => $p->valor_alumno === null ? null : (float) $p->valor_alumno,
                'vigencia_dias' => $p->vigencia_dias === null ? null : (int) $p->vigencia_dias,
            ]);
        // Las inactivas también, si tienen tarifa: sus cursos pueden seguir cobrando.
        $modalidades = DB::table('modalidades')
            ->where(fn ($q) => $q->where('estado', 1)->orWhereIn('id', DB::table('escalas_precio')->select('modalidad_id')))
            ->orderBy('nombre')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'nombre' => $m->nombre,
                'tipo_cobro' => $m->tipo_cobro,
                'clases_por_ciclo' => $m->clases_por_ciclo === null ? null : (int) $m->clases_por_ciclo,
                'duracion_minutos' => (int) $m->duracion_minutos,
                'estado' => (bool) $m->estado,
            ] + array_map('array_values', Tarifas::escalas($m->id)));
        return ['modalidades' => $modalidades, 'personalizadas' => $personalizadas];
    }

    /**
     * GET /v1/tarifas → { modalidades: [{ id, nombre, …, individual: [totales], pareja: [totales] }], personalizadas: [paquetes] }
     * (en las escalas, posición 0 = 1 curso).
     */
    public function index()
    {
        return response($this->datos(), Response::HTTP_OK);
    }

    /**
     * PUT /v1/tarifas: reemplaza las escalas de cada modalidad que venga (una lista vacía quita esa
     * escala) y, si viene `personalizadas`, crea/actualiza esos paquetes y desactiva los que ya no estén.
     */
    public function update(Request $request)
    {
        $datos = $request->all();
        $validator = Validator::make($datos, [
            'modalidades' => 'array|nullable',
            'modalidades.*.id' => 'integer|required|distinct|exists:modalidades,id',
            'modalidades.*.individual' => 'array|nullable|max:20',
            'modalidades.*.individual.*' => 'numeric|min:0',
            'modalidades.*.pareja' => 'array|nullable|max:20',
            'modalidades.*.pareja.*' => 'numeric|min:0',
            'personalizadas' => 'array|nullable|max:30',
            'personalizadas.*.id' => 'integer|nullable|exists:planes,id',
            'personalizadas.*.nombre' => 'string|required|max:100',
            'personalizadas.*.clases' => 'integer|required|min:1',
            'personalizadas.*.valor' => 'numeric|required|min:0',
            'personalizadas.*.valor_alumno' => 'numeric|nullable|min:0',
            'personalizadas.*.vigencia_dias' => 'integer|nullable|min:1',
        ], [], [
            'modalidades.*.individual.*' => 'total de la escala individual',
            'modalidades.*.pareja.*' => 'total de la escala de pareja',
            'personalizadas.*.nombre' => 'nombre del paquete',
            'personalizadas.*.clases' => 'número de clases',
            'personalizadas.*.valor' => 'precio',
            'personalizadas.*.valor_alumno' => 'precio para alumnos',
            'personalizadas.*.vigencia_dias' => 'días de vigencia',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        // El total no puede bajar al agregar un curso (ese curso saldría con valor negativo).
        $nombres = DB::table('modalidades')->pluck('nombre', 'id');
        foreach ($datos['modalidades'] ?? [] as $modalidad) {
            foreach (Tarifas::TIPOS as $tipo) {
                $totales = array_values($modalidad[$tipo] ?? []);
                foreach ($totales as $i => $total) {
                    if ($i > 0 && (float) $total < (float) $totales[$i - 1]) {
                        return response(get_response_body([
                            "{$nombres[$modalidad['id']]}: en la escala " . self::NOMBRES[$tipo] . ', el total de ' . ($i + 1)
                                . ' cursos no puede ser menor que el de ' . $i . '.',
                        ]), Response::HTTP_BAD_REQUEST);
                    }
                }
            }
        }

        try {
            DB::transaction(function () use ($datos) {
                foreach ($datos['modalidades'] ?? [] as $modalidad) {
                    Tarifas::guardarEscalas((int) $modalidad['id'], $modalidad);
                }
                if (array_key_exists('personalizadas', $datos)) {
                    $this->guardarPersonalizadas($datos['personalizadas'] ?? []);
                }
            });
            return response(get_response_body(
                ['Las tarifas han sido guardadas. Aplican desde el siguiente ciclo de cada alumno.', 1],
                $this->datos()
            ), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Los paquetes se guardan en la tabla planes (periodicidad 'paquete'); los ya vendidos conservan su valor. */
    private function guardarPersonalizadas(array $paquetes): void
    {
        $conservados = [];
        foreach ($paquetes as $p) {
            $dto = [
                'nombre' => $p['nombre'],
                'num_clases' => $p['clases'],
                'valor' => $p['valor'],
                'valor_alumno' => $p['valor_alumno'] ?? null,
                'vigencia_dias' => $p['vigencia_dias'] ?? null,
            ];
            if (!empty($p['id'])) {
                // Solo se tocan planes de paquete (un id de otro tipo de plan se ignora).
                if (!DB::table('planes')->where('id', $p['id'])->where('periodicidad', 'paquete')->exists()) {
                    continue;
                }
                $dto['id'] = (int) $p['id'];
            } else {
                $dto += ['periodicidad' => 'paquete', 'estado' => 1];
            }
            $conservados[] = Plan::modificarOCrear($dto)['id'];
        }

        // Los que se quitaron de la lista se desactivan (no se borran: pueden tener paquetes vendidos).
        $quitados = DB::table('planes')->where('periodicidad', 'paquete')->where('estado', 1)->whereNotIn('id', $conservados)->pluck('id');
        foreach ($quitados as $id) {
            Plan::modificarOCrear(['id' => $id, 'estado' => 0]);
        }
    }
}
