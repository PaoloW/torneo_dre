@extends('layouts.app')

@section('titulo', 'Importar CSV')

@section('content')
<div class="contenedor">
    <nav class="migas" aria-label="Ruta de navegación">
        <span>Administración / Importar CSV</span>
    </nav>

    <div class="tarjeta">
        <h1 class="tarjeta-titulo">Importar clasificados desde CSV</h1>
        <p class="texto-suave">
            Acepta los archivos de 28 columnas (DATA GRAL.MACRO) y de 31 columnas (GRAL-METRICAS).
            La primera fila debe contener los encabezados.
        </p>

        <form method="POST" action="{{ route('admin.importar.procesar') }}" enctype="multipart/form-data" class="form-importar">
            @csrf
            <label class="campo">
                <span>Archivo CSV</span>
                <input type="file" name="archivo" accept=".csv,.txt" required>
            </label>

            <label class="check">
                <input type="checkbox" name="fresh" value="1">
                <span>Vaciar la tabla antes de importar (solo para el primer archivo)</span>
            </label>

            <div class="botonera">
                <button type="submit" class="btn btn-primario">Importar</button>
            </div>
        </form>

        @error('archivo')
            <p class="error">{{ $message }}</p>
        @enderror

        @if (session('importar_salida'))
            <pre class="salida {{ session('importar_ok') ? 'ok' : 'fallo' }}">{{ session('importar_salida') }}</pre>
        @endif
    </div>
</div>
@endsection
