<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    // GET /login
    public function mostrarLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('inicio'); // F19
        }

        return view('auth.login');
    }

    // POST /login (F4: acceso por usuario simple, sin email)
    public function login(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], [
            'usuario' => 'usuario',
            'password' => 'contraseña',
        ]);

        if (Auth::attempt($credenciales, $request->boolean('recordar'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('inicio')); // F19
        }

        return back()
            ->withErrors(['usuario' => 'Credenciales incorrectas.'])
            ->onlyInput('usuario');
    }

    // POST /logout
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login'); // F19
    }
}
