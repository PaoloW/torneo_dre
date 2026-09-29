<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Torneo Macro Regional 2026')</title>
    <link href="https://cdn.jsdelivr.net/npm/beercss@latest/dist/cdn/beer.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body>
<header class="cabecera">
    <div class="cabecera-izq">
        <span class="cabecera-titulo">Torneo Macro Regional 2026</span>
        <span id="estado-guardado" class="estado-guardado" aria-live="polite"></span>
    </div>
    @auth
        <div class="cabecera-der">
            @if(auth()->user()->rol === 'admin')
                <a class="cabecera-enlace{{ request()->is('admin/resultados') ? ' activo' : '' }}"
                   href="{{ url('/admin/resultados') }}">Resultados</a>
                <a class="cabecera-enlace{{ request()->is('admin/importar') ? ' activo' : '' }}"
                   href="{{ url('/admin/importar') }}">Importar CSV</a>
                <a class="cabecera-enlace{{ request()->is('admin/usuarios') ? ' activo' : '' }}"
                   href="{{ url('/admin/usuarios') }}">Usuarios</a>
            @endif
            <span class="cabecera-usuario">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="form-logout">
                @csrf
                <button type="submit" class="btn btn-secundario">Salir</button>
            </form>
        </div>
    @endauth
</header>

<main class="principal">
    @yield('content')
</main>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.3/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-ui-touch-punch@0.2.3/jquery.ui.touch-punch.min.js"></script>
@stack('scripts')
</body>
</html>
