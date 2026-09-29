@extends('layouts.app')

@section('titulo', 'Iniciar sesión')

@section('content')
<div class="contenedor-login">
    <form method="POST" action="{{ route('login') }}" class="tarjeta-login">
        @csrf
        <h1 class="login-titulo">Torneo Macro Regional 2026</h1>
        <p class="login-subtitulo">Orden de clasificados — jueces</p>

        <label class="campo">
            <span>Usuario</span>
            <input type="text" name="usuario" value="{{ old('usuario') }}" required autofocus autocomplete="username">
        </label>

        <label class="campo">
            <span>Contraseña</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>

        @error('usuario')
            <p class="error">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn btn-primario">Ingresar</button>
    </form>
</div>
@endsection
