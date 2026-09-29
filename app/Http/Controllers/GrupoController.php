<?php

namespace App\Http\Controllers;

use App\Models\Observacion;
use App\Models\Participante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GrupoController extends Controller
{
    /**
     * GET /api/grupos → filas planas por (categoría, disciplina, género, clase, prueba) con conteos.
     * F13: el front arma el cascada Categoría → Disciplina → Género → Clase → Prueba
     * y muestra el nº de clasificados en cada opción.
     */
    public function grupos(): JsonResponse
    {
        $filas = DB::table('participantes')
            ->selectRaw('categoria, disciplina, genero, COALESCE(clase_variante, "") AS clase, COALESCE(dsc_prueba, "") AS prueba, COUNT(*) AS total')
            ->whereNotNull('disciplina')
            ->where('disciplina', '<>', '')
            ->groupBy('categoria', 'disciplina', 'genero', DB::raw('COALESCE(clase_variante, "")'), DB::raw('COALESCE(dsc_prueba, "")'))
            ->orderBy('categoria')
            ->orderBy('disciplina')
            ->orderBy('genero')
            ->orderBy('clase')
            ->orderBy('prueba')
            ->get();

        return response()->json($filas);
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
            'genero' => ['required', 'string'], // F13: DAMAS / VARONES
            'clase' => ['required', 'string'],
            'prueba' => ['nullable', 'string'],
        ]);

        [$participantes, $observacion] = $this->buscarGrupo($datos);

        return response()->json(['participantes' => $participantes, 'observacion' => $observacion]);
    }

    /**
     * GET /resultados/pdf?disciplina=&categoria=&clase=&prueba=
     * F3: vista imprimible del grupo filtrado (preview web → botón "Imprimir / Guardar PDF").
     */
    public function pdf(Request $request): View
    {
        $datos = $request->validate([
            'disciplina' => ['required', 'string'],
            'categoria' => ['required', 'string'],
            'genero' => ['required', 'string'], // F13: DAMAS / VARONES
            'clase' => ['required', 'string'],
            'prueba' => ['nullable', 'string'],
        ]);

        [$participantes, $observacion] = $this->buscarGrupo($datos);

        return view('juez.pdf', [
            'grupo' => [
                'categoria' => $datos['categoria'],
                'disciplina' => $datos['disciplina'],
                'genero' => $datos['genero'],
                'clase' => $datos['clase'],
                'prueba' => $datos['prueba'] ?? '',
            ],
            'participantes' => $participantes,
            'observacion' => $observacion,
            'usuario' => $request->user(),
            'generado' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Consulta compartida por lista() y pdf(): participantes del grupo + observación del juez.
     */
    private function buscarGrupo(array $datos): array
    {
        $prueba = $datos['prueba'] ?? '';

        $participantes = Participante::query()
            ->where('disciplina', $datos['disciplina'])
            ->where('categoria', $datos['categoria'])
            ->where('genero', $datos['genero']) // F13: DAMAS y VARONES por separado
            ->where('clase_variante', $datos['clase'])
            ->whereRaw('COALESCE(dsc_prueba, "") = ?', [$prueba])
            ->orderByRaw('puesto IS NULL')   // sin puesto → al final
            ->orderBy('puesto')
            ->orderBy('id')
            ->get([
                'id', 'nombres', 'apepat', 'apemat', 'ie', 'ugel', 'regionie', 'provinciaie',
                'nivel_educativo', 'grado', 'genero', 'tipo_documento', 'numdoc_participante',
                'tipo_participante', 'puesto', 'dsc_prueba', 'metrica', 'marca',
            ]);

        $observacion = Observacion::query()
            ->where('disciplina', $datos['disciplina'])
            ->where('categoria', $datos['categoria'])
            ->where('genero', $datos['genero']) // F13
            ->where('clase', $datos['clase'])
            ->where('prueba', $prueba)
            ->value('texto');

        return [$participantes, $observacion];
    }

    /**
     * POST /api/guardar-orden → UPDATE en transacción + auditoría (§5.6).
     * Última escritura gana. Los del grupo que no vengan en items quedan en NULL.
     */
    public function guardar(Request $request): JsonResponse
    {
        // F2: el admin no puede editar nada (solo lectura también en la API)
        if ($request->user()?->rol === 'admin') {
            return response()->json(['message' => 'La vista de administración es de solo lectura'], 403);
        }

        $datos = $request->validate([
            'grupo.disciplina' => ['required', 'string'],
            'grupo.categoria' => ['required', 'string'],
            'grupo.genero' => ['required', 'string'], // F13
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
                ->where('genero', $grupo['genero']) // F13: no toca al otro género
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
        // F2: el admin no puede editar nada (solo lectura también en la API)
        if ($request->user()?->rol === 'admin') {
            return response()->json(['message' => 'La vista de administración es de solo lectura'], 403);
        }

        $datos = $request->validate([
            'grupo.disciplina' => ['required', 'string'],
            'grupo.categoria' => ['required', 'string'],
            'grupo.genero' => ['required', 'string'], // F13
            'grupo.clase' => ['required', 'string'],
            'grupo.prueba' => ['nullable', 'string'],
            'texto' => ['nullable', 'string'],
        ]);

        $grupo = $datos['grupo'];

        Observacion::updateOrCreate(
            [
                'disciplina' => $grupo['disciplina'],
                'categoria' => $grupo['categoria'],
                'genero' => $grupo['genero'],
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
