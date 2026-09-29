<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    // GET /admin/usuarios → listado + formularios de crear/editar (F5)
    public function index(): View
    {
        return view('admin.usuarios', [
            'usuarios' => User::orderBy('rol', 'desc')->orderBy('id')->get(),
        ]);
    }

    // POST /admin/usuarios/crear → usuario nuevo (usuario, nombre, contraseña, rol)
    public function crear(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:users,usuario'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:6', 'max:64'],
            'rol' => ['required', 'in:admin,juez'],
        ], [
            'usuario.regex' => 'El usuario solo puede tener letras, números, punto, guion o guion bajo.',
            'usuario.unique' => 'Ese usuario ya existe.',
        ], [
            'usuario' => 'usuario',
            'name' => 'nombre',
            'password' => 'contraseña',
            'rol' => 'rol',
        ]);

        User::create([
            'name' => $datos['name'],
            'usuario' => $datos['usuario'],
            'password' => Hash::make($datos['password']),
            'rol' => $datos['rol'],
        ]);

        return redirect('/admin/usuarios')->with('exito', "Usuario {$datos['usuario']} creado.");
    }

    // POST /admin/usuarios/{id}/actualizar → edita nombre y/o contraseña (vacío = no cambia)
    public function actualizar(Request $request, int $id): RedirectResponse
    {
        $usuario = User::findOrFail($id);

        // campos únicos por fila (name_5, password_5) para no mezclar formularios
        $datos = $request->validate([
            "name_{$id}" => ['required', 'string', 'max:120'],
            "password_{$id}" => ['nullable', 'string', 'min:6', 'max:64'],
        ], [
            'name_*' => 'El nombre es obligatorio.',
            'password_*.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ], [
            "name_{$id}" => 'nombre',
            "password_{$id}" => 'contraseña',
        ]);

        $actualizaciones = [
            'name' => $datos["name_{$id}"],
            'updated_at' => now(),
        ];

        if (! empty($datos["password_{$id}"])) {
            $actualizaciones['password'] = Hash::make($datos["password_{$id}"]);
        }

        DB::table('users')->where('id', $usuario->id)->update($actualizaciones);

        return redirect('/admin/usuarios')->with('exito', "Usuario {$usuario->usuario} actualizado.");
    }
}
