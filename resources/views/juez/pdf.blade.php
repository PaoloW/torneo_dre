<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resultados — {{ $grupo['disciplina'] }}</title>
    <style>
        :root { --acento: #4f46e5; --borde: #e4e4e7; --suave: #71717a; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1rem; background: #f4f4f5; color: #18181b;
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }

        /* toolbar preview web (no se imprime) */
        .acciones { max-width: 900px; margin: 0 auto .75rem; display: flex; gap: .5rem; justify-content: flex-end; }
        .btn { min-height: 40px; padding: 0 1rem; border: 1px solid transparent; border-radius: .5rem;
               font-size: .9rem; font-weight: 600; cursor: pointer; }
        .btn-primario { background: var(--acento); color: #fff; }
        .btn-secundario { background: #fff; color: #18181b; border-color: var(--borde); }

        .hoja { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid var(--borde);
                border-radius: .5rem; padding: 1.25rem; }
        h1 { margin: 0; font-size: 1.15rem; }
        h2 { margin: 0 0 .35rem; font-size: .95rem; }
        .ruta { margin: .35rem 0; font-weight: 700; color: var(--acento); }
        .meta { margin: 0 0 .75rem; font-size: .82rem; color: var(--suave); }

        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th, td { border: 1px solid var(--borde); padding: .4rem .5rem; text-align: left; vertical-align: top; }
        th { background: #f4f4f5; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; }
        td small { color: var(--suave); }
        .num { text-align: center; white-space: nowrap; }
        .puesto { font-weight: 800; }
        .puesto .empate { display: inline-block; margin-left: .25rem; padding: 0 .35rem; border-radius: .35rem;
                          background: #fef3c7; color: #92400e; font-size: .65rem; font-weight: 700;
                          text-transform: uppercase; vertical-align: middle; }
        .sin-puesto { color: #a1a1aa; }
        .vacio { text-align: center; color: var(--suave); padding: 1.5rem; }

        .observacion { margin-top: 1rem; border: 1px dashed var(--borde); border-radius: .5rem; padding: .6rem .75rem; }
        .observacion p { margin: 0; font-size: .85rem; }
        .firmas { display: flex; gap: 2rem; margin-top: 2.5rem; }
        .firma { flex: 1; text-align: center; font-size: .8rem; }
        .firma .linea { border-top: 1px solid #18181b; padding-top: .35rem; }

        @media print {
            @page { size: A4; margin: 12mm; }
            body { background: #fff; padding: 0; }
            .acciones { display: none; }
            .hoja { border: none; border-radius: 0; padding: 0; max-width: none; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="acciones">
    <button type="button" class="btn btn-secundario" onclick="window.close()">Cerrar</button>
    <button type="button" class="btn btn-primario" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

@php
    $conteos = $participantes->filter(fn ($p) => $p->puesto !== null)->countBy(fn ($p) => $p->puesto);
    // F13: mismo orden que las migas del juez → Categoría / Disciplina / Género / Clase / Prueba
    $ruta = $grupo['categoria'] . ' / ' . $grupo['disciplina'] . ' / ' . $grupo['genero'] . ' / ' . $grupo['clase']
        . ($grupo['prueba'] !== '' ? ' / ' . $grupo['prueba'] : '');
@endphp

<div class="hoja">
    <h1>Torneo Macro Regional 2026 — Resultados</h1>
    <p class="ruta">{{ $ruta }}</p>
    <p class="meta">
        {{ $participantes->count() }} clasificados ·
        Generado el {{ $generado }} por {{ $usuario->name ?? '—' }} ({{ $usuario->rol ?? '' }})
    </p>

    @if ($participantes->isEmpty())
        <p class="vacio">Sin participantes para este grupo.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th class="num" style="width: 7rem">Puesto</th>
                    <th style="width: 8rem">Documento</th>
                    <th>Participante</th>
                    <th>Institución educativa</th>
                    <th>Prueba · Métrica · Marca</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($participantes as $p)
                    <tr>
                        <td class="num puesto">
                            @if ($p->puesto === null)
                                <span class="sin-puesto">—</span>
                            @else
                                {{ $p->puesto }}°
                                @if (($conteos[$p->puesto] ?? 0) > 1)
                                    <span class="empate">empate</span>
                                @endif
                            @endif
                        </td>
                        <td>{{ trim(($p->tipo_documento ?: '') . ' ' . ($p->numdoc_participante ?: '')) ?: '—' }}</td>
                        <td>
                            {{ trim($p->nombres . ' ' . $p->apepat . ' ' . $p->apemat) }}
                            <br><small>{{ $p->grado }} · {{ $p->tipo_participante }} · {{ $p->genero }}</small>
                        </td>
                        <td>
                            {{ $p->ie }}
                            <br><small>{{ $p->ugel }} · {{ $p->regionie }}{{ $p->provinciaie ? ' · ' . $p->provinciaie : '' }}</small>
                        </td>
                        <td>{{ trim(($p->dsc_prueba ?: '—') . ' · ' . ($p->metrica ?: '') . ' · ' . ($p->marca ?: '')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($observacion)
        <section class="observacion">
            <h2>Observación del juez</h2>
            <p>{!! nl2br(e($observacion)) !!}</p>
        </section>
    @endif

    <div class="firmas">
        <div class="firma"><div class="linea">Juez / Fiscalizador</div></div>
        <div class="firma"><div class="linea">Autoridad de control</div></div>
    </div>
</div>
</body>
</html>

