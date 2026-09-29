@extends('layouts.app')

@section('titulo', $soloLectura ? 'Resultados — solo lectura' : 'Orden de clasificados')

@section('content')
<div class="contenedor{{ $soloLectura ? ' solo-lectura' : '' }}" id="vista-resultados"
     data-solo-lectura="{{ $soloLectura ? 'true' : 'false' }}">
    <nav class="migas" aria-label="Ruta de navegación" id="migas">
        <span>Disciplina</span>
    </nav>

    <div class="navegacion">
        <label class="campo">
            <span>Disciplina</span>
            <select id="sel-disciplina">
                <option value="">Seleccione…</option>
            </select>
        </label>

        <label class="campo" id="campo-categoria" hidden>
            <span>Categoría</span>
            <select id="sel-categoria">
                <option value="">Seleccione…</option>
            </select>
        </label>

        <label class="campo" id="campo-clase" hidden>
            <span>Clase</span>
            <select id="sel-clase">
                <option value="">Seleccione…</option>
            </select>
        </label>

        <label class="campo" id="campo-prueba" hidden>
            <span>Prueba</span>
            <select id="sel-prueba">
                <option value="">Todas</option>
            </select>
        </label>

        <span id="badge-clasificados" class="badge-contador" hidden>0 clasificados</span>
    </div>

    <div class="barra">
        <input type="search" id="buscador" class="buscador" placeholder="Buscar por nombre o IE…"
               aria-label="Buscar participante" autocomplete="off">
        @if($soloLectura)
            <span class="badge-solo-lectura" title="Vista de resultados en modo lectura">Solo lectura</span>
        @endif
        <button type="button" id="btn-pdf" class="btn btn-secundario" disabled>Exportar PDF</button>
        <button type="button" id="btn-limpiar" class="btn btn-secundario" disabled>Limpiar orden</button>
    </div>

    <details class="observacion" id="panel-observacion" hidden>
        <summary>{{ $soloLectura ? 'Observación del juez (solo lectura)' : 'Observación del juez (opcional)' }}</summary>
        <textarea id="observacion" rows="3" placeholder="Escriba aquí su observación para este grupo…"></textarea>
    </details>

    <ul id="lista" class="lista" aria-label="Clasificados"></ul>

    <p id="vacio" class="vacio" hidden>Seleccione disciplina, categoría y clase para ver los clasificados.</p>
</div>

<dialog id="modal-historial" class="modal">
    <div class="modal-cabecera">
        <h2>Historial de puesto</h2>
        <button type="button" class="btn-icono" id="btn-cerrar-historial" aria-label="Cerrar">✕</button>
    </div>
    <p id="historial-nombre" class="modal-subtitulo"></p>
    <table class="tabla-historial">
        <thead>
            <tr>
                <th>Fecha/hora</th>
                <th>Juez</th>
                <th>Puesto</th>
            </tr>
        </thead>
        <tbody id="historial-cuerpo"></tbody>
    </table>
    <p id="historial-vacio" class="vacio" hidden>Sin cambios registrados.</p>
</dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/juez.js') }}?v={{ filemtime(public_path('js/juez.js')) }}"></script>
@endpush
