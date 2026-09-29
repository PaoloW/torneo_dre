<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JuezController extends Controller
{
    // GET /juez → pantalla de lista (selects encadenados + drag & drop)
    public function index(): View|RedirectResponse
    {
        // F2: el admin solo consulta, se le envía a la vista de solo lectura
        if (request()->user() && request()->user()->rol === 'admin') {
            return redirect('/admin/resultados');
        }

        return view('juez.index', ['soloLectura' => false]);
    }

    // GET /admin/resultados → misma interfaz del juez en modo SOLO LECTURA (F2)
    public function resultados(): View
    {
        return view('juez.index', ['soloLectura' => true]);
    }
}
