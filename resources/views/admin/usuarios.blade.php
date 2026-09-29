@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('content')
<div class="contenedor">
    <nav class="migas" aria-label="Ruta de navegación">
        <span>Administración / Usuarios</span>
    </nav>

    @if (session('exito'))
        <p class="texto-exito">{{ session('exito') }}</p>
    @endif

    {{-- F5: crear usuario nuevo --}}
    <div class="tarjeta">
        <h1 class="tarjeta-titulo">Crear usuario</h1>

        <form method="POST" action="{{ route('admin.usuarios.crear') }}" class="form-usuarios">
            @csrf
            <label class="campo">
                <span>Usuario</span>
                <input type="text" name="usuario" value="{{ old('usuario') }}" required
                       minlength="3" maxlength="60" pattern="[A-Za-z0-9_.\-]+"
                       title="Solo letras, números, punto, guion o guion bajo">
            </label>

            <label class="campo">
                <span>Nombre</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="120">
            </label>

            <label class="campo">
                <span>Contraseña</span>
                <input type="text" name="password" value="{{ old('password') }}" required
                       minlength="6" maxlength="64" autocomplete="new-password">
            </label>

            <label class="campo">
                <span>Rol</span>
                <select name="rol" required>
                    <option value="juez" {{ old('rol') === 'juez' ? 'selected' : '' }}>Juez</option>
                    <option value="admin" {{ old('rol') === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </label>

            <div class="botonera">
                <button type="submit" class="btn btn-primario">Crear</button>
            </div>
        </form>

        @foreach (['usuario', 'name', 'password', 'rol'] as $campo)
            @error($campo)
                <p class="error">{{ $message }}</p>
            @enderror
        @endforeach
    </div>

    {{-- F5: editar nombre y/o contraseña de usuarios existentes --}}
    <div class="tarjeta tarjeta-lista">
        <h1 class="tarjeta-titulo">Usuarios existentes ({{ $usuarios->count() }})</h1>

        @forelse ($usuarios as $u)
            <form method="POST" action="{{ route('admin.usuarios.actualizar', $u->id) }}" class="fila-usuario">
                @csrf
                <div class="fila-usuario-datos">
                    <strong>{{ $u->name }}</strong>
                    <span class="texto-suave">
                        {{ $u->usuario }}
                        · <span class="etiqueta-rol {{ $u->rol === 'admin' ? 'admin' : '' }}">{{ $u->rol }}</span>
                    </span>
                </div>

                <label class="campo">
                    <span>Nombre</span>
                    <input type="text" name="name_{{ $u->id }}"
                           value="{{ old('name_' . $u->id, $u->name) }}" required maxlength="120">
                </label>

                <label class="campo">
                    <span>Nueva contraseña</span>
                    <input type="text" name="password_{{ $u->id }}"
                           value="{{ old('password_' . $u->id) }}"
                           minlength="6" maxlength="64" placeholder="Vacío = no cambiar" autocomplete="new-password">
                </label>

                <div class="botonera">
                    <button type="submit" class="btn btn-primario">Guardar</button>
                </div>

                @error('name_' . $u->id)
                    <p class="error">{{ $message }}</p>
                @enderror
                @error('password_' . $u->id)
                    <p class="error">{{ $message }}</p>
                @enderror
            </form>
        @empty
            <p class="vacio">Sin usuarios registrados.</p>
        @endforelse
    </div>
</div>
@endsection
