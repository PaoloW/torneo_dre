<?php

namespace App\Http\Controllers;

use App\Models\Observacion;
use App\Models\Participante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrupoController extends Controller
{
    /**
     * GET /api/grupos → árbol disciplina > categoría > clase (con pruebas) y conteos.
     */
    public function grupos(): JsonResponse
    {
        $filas = DB::table('participantes')
            ->selectRaw('disciplina, categoria, clase_variante, COALESCE(dsc_prueba, "") AS prueba, COUNT(*) AS total')
            ->whereNotNull('disciplina')
            ->where('disciplina', '<>', '')
            ->groupBy('disciplina', 'categoria', 'clase_variante', DB::raw('COALESCE(dsc_prueba, "")'))
            ->orderBy('disciplina')
            ->orderBy('categoria')
            ->orderBy('clase_variante')
            ->orderBy('prueba')
            ->get();

        $arbol = [];

        foreach ($filas as $fila) {
            $disciplina = $fila->disciplina;
            $categoria = $fila->categoria;
            $clase = $fila->clase_variante;

            if (! isset($arbol[$disciplina])) {
                $arbol[$disciplina] = ['disciplina' => $disciplina, 'total' => 0, 'categorias' => []];
            }
            if (! isset($arbol[$disciplina]['categorias'][$categoria])) {
                $arbol[$disciplina]['categorias'][$categoria] = ['categoria' => $categoria, 'total' => 0, 'clases' => []];
            }
            if (! isset($arbol[$disciplina]['categorias'][$categoria]['clases'][$clase])) {
                $arbol[$disciplina]['categorias'][$categoria]['clases'][$clase] = [
                    'clase' => $clase, 'total' => 0, 'pruebas' => [],
                ];
            }

            $arbol[$disciplina]['total'] += $fila->total;
            $arbol[$disciplina]['categorias'][$categoria]['total'] += $fila->total;
            $arbol[$disciplina]['categorias'][$categoria]['clases'][$clase]['total'] += $fila->total;
            $arbol[$disciplina]['categorias'][$categoria]['clases'][$clase]['pruebas'][] = $fila->prueba;
        }

        // reindexa arrays anidados para que lleguen como listas JSON
        $salida = [];
        foreach ($arbol as $nodo) {
            $categorias = [];
            foreach ($nodo['categorias'] as $cat) {
                $clases = [];
                foreach ($cat['clases'] as $cla) {
                    $clases[] = [
                        'clase' => $cla['clase'],
                        'total' => $cla['total'],
                        'pruebas' => array_values(array_unique($cla['pruebas'])), // F1: sin duplicados
                        'con_pruebas' => array_filter($cla['pruebas']) !== [],
                    ];
                }
                $cat['clases'] = $clases;
                $categorias[] = $cat;
            }
            $nodo['categorias'] = $categorias;
            $salida[] = $nodo;
        }

        return response()->json($salida);
    }

    /**
     * GET /api/participantes?disciplina=&categoria=&clase=&prueba=
     * Orden: puesto ASC (NULL al final), id ASC. Devuelve también la observación del grupo.
     */
    public function lista(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'disciplina' => ['required', 'string'],
            'categoria' => ['required', 'string'],
            'clase' => ['required', 'string'],
            'prueba' => ['nullable', 'string'],
        ]);

        $prueba = $datos['prueba'] ?? '';

        $participantes = Participante::query()
            ->where('disciplina', $datos['disciplina'])
            ->where('categoria', $datos['categoria'])
            ->where('clase_variante', $datos['clase'])
            ->whereRaw('COALESCE(dsc_prueba, "") = ?', [$prueba])
            ->orderByRaw('puesto IS NULL')   // sin puesto → al final
            ->orderBy('puesto')
            ->orderBy('id')
            ->get([
                'id', 'nombres', 'apepat', 'apemat', 'ie', 'ugel', 'regionie', 'provinciaie',
                'grado', 'genero', 'numdoc_participante', 'tipo_participante', 'puesto',
                'dsc_prueba', 'metrica', 'marca',
            ]);

        $observacion = Observacion::query()
            ->where('disciplina', $datos['disciplina'])
            ->where('categoria', $datos['categoria'])
            ->where('clase', $datos['clase'])
            ->where('prueba', $prueba)
            ->value('texto');

        return response()->json(['participantes' => $participantes, 'observacion' => $observacion]);
    }

    /**
     * POST /api/guardar-orden → UPDATE en transacción + auditoría (§5.6).
     * Última escritura gana. Los del grupo que no vengan en items quedan en NULL.
     */
    public function guardar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'grupo.disciplina' => ['required', 'string'],
            'grupo.categoria' => ['required', 'string'],
            'grupo.clase' => ['required', 'string'],
            'grupo.prueba' => ['nullable', 'string'],
            'items' => ['present', 'array'],   // 'present' admite [] (botón Limpiar orden)
            'items.*.id' => ['required', 'integer'],
            'items.*.puesto' => ['nullable', 'integer', 'min:1'],
            'limpiar' => ['sometimes', 'boolean'],
        ]);

        $grupo = $datos['grupo'];
        $prueba = $grupo['prueba'] ?? '';

        // mapa id => puesto (los ausentes quedan en NULL)
        $nuevos = [];
        foreach ($datos['items'] as $item) {
            $nuevos[(int) $item['id']] = $item['puesto'] === null ? null : (int) $item['puesto'];
        }

        $usuario = $request->user();

        DB::transaction(function () use ($grupo, $prueba, $nuevos, $usuario) {
            $filas = DB::table('participantes')
                ->where('disciplina', $grupo['disciplina'])
                ->where('categoria', $grupo['categoria'])
                ->where('clase_variante', $grupo['clase'])
                ->whereRaw('COALESCE(dsc_prueba, "") = ?', [$prueba])
                ->lockForUpdate()
                ->get(['id', 'puesto']);

            foreach ($filas as $fila) {
                $anterior = $fila->puesto === null ? null : (int) $fila->puesto;
                $nuevo = array_key_exists($fila->id, $nuevos) ? $nuevos[$fila->id] : null;

                if ($nuevo === $anterior) {
                    continue; // sin cambio real → no audita
                }

                DB::table('participantes')->where('id', $fila->id)->update(['puesto' => $nuevo]);

                DB::table('puesto_auditoria')->insert([
                    'participante_id' => $fila->id,
                    'puesto_anterior' => $anterior,
                    'puesto_nuevo' => $nuevo,
                    'user_id' => $usuario?->id,
                    'user_nombre' => $usuario?->name,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/guardar-observacion → upsert por clave de grupo.
     */
    public function observacion(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'grupo.disciplina' => ['required', 'string'],
            'grupo.categoria' => ['required', 'string'],
            'grupo.clase' => ['required', 'string'],
            'grupo.prueba' => ['nullable', 'string'],
            'texto' => ['nullable', 'string'],
        ]);

        $grupo = $datos['grupo'];

        Observacion::updateOrCreate(
            [
                'disciplina' => $grupo['disciplina'],
                'categoria' => $grupo['categoria'],
                'clase' => $grupo['clase'],
                'prueba' => $grupo['prueba'] ?? '',
            ],
            [
                'texto' => $datos['texto'] ?? null,
                'user_id' => $request->user()?->id,
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/historial/{participante} → últimas 20 modificaciones de puesto.
     */
    public function historial(int $participante): JsonResponse
    {
        $filas = DB::table('puesto_auditoria')
            ->where('participante_id', $participante)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['created_at', 'user_nombre', 'puesto_anterior', 'puesto_nuevo']);

        return response()->json($filas);
    }
}
