@extends('layouts.app')

@section('titulo', $soloLectura ? 'Resultados — solo lectura' : 'Orden de clasificados')

@section('content')
<div class="contenedor{{ $soloLectura ? ' solo-lectura' : '' }}" id="vista-resultados"
     data-solo-lectura="{{ $soloLectura ? 'true' : 'false' }}">
    <nav class="migas" aria-label="Ruta de navegación" id="migas">
        <span>Categoría</span>
    </nav>

    <div class="navegacion">
        <!-- F13: orden de filtros Categoría → Disciplina → Género → Clase → Prueba -->
        <label class="campo" id="campo-categoria">
            <span>Categoría</span>
            <div class="filtro" id="filtro-categoria"></div>
        </label>

        <label class="campo" id="campo-disciplina" hidden>
            <span>Disciplina</span>
            <div class="filtro" id="filtro-disciplina"></div>
        </label>

        <label class="campo" id="campo-genero" hidden>
            <span>Género</span>
            <div class="filtro" id="filtro-genero"></div>
        </label>

        <label class="campo" id="campo-clase" hidden>
            <span>Clase</span>
            <div class="filtro" id="filtro-clase"></div>
        </label>

        <label class="campo" id="campo-prueba" hidden>
            <span>Prueba</span>
            <div class="filtro" id="filtro-prueba"></div>
        </label>

        <span id="badge-clasificados" class="badge-contador" hidden>0 clasificados</span>
    </div>

    <div class="barra">
        @if($soloLectura)
            <span class="badge-solo-lectura" title="Vista de resultados en modo lectura">Solo lectura</span>
        @endif
        <button type="button" id="btn-pdf" class="btn btn-secundario" disabled>Exportar PDF</button>
        <button type="button" id="btn-limpiar" class="btn btn-secundario" disabled>Limpiar orden</button>
    </div>

    <!-- F8: buscador en su propia línea, al inicio de la lista (siempre visible, también en móvil) -->
    <div class="busqueda">
        <input type="search" id="buscador" class="buscador" placeholder="Buscar por DNI o nombre…"
               aria-label="Buscar por DNI o nombre" autocomplete="off">
        <button type="button" id="btn-limpiar-buscador" class="buscador-x" hidden
                aria-label="Limpiar búsqueda" title="Limpiar búsqueda">✕</button>
    </div>

    <ul id="lista" class="lista" aria-label="Clasificados"></ul>

    <p id="vacio" class="vacio" hidden>Seleccione categoría, disciplina, género y clase para ver los clasificados.</p>

    <!-- F10: observación movida al final de la vista -->
    <br>
    <details class="observacion" id="panel-observacion" hidden>
        <summary>{{ $soloLectura ? 'Observación del juez (solo lectura)' : 'Observación del juez (opcional)' }}</summary>
        <textarea id="observacion" rows="3" placeholder="Escriba aquí su observación para este grupo…"></textarea>
    </details>
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
