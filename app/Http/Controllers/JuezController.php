<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class JuezController extends Controller
{
    // GET /juez → pantalla de lista (selects encadenados + drag & drop)
    public function index(): View
    {
        return view('juez.index');
    }
}
