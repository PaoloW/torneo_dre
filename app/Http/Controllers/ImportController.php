<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ImportController extends Controller
{
    // GET /admin/importar
    public function mostrarFormulario(): View
    {
        return view('admin.importar');
    }

    // POST /admin/importar → sube el CSV y ejecuta torneo:importar
    public function importar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'fresh' => ['sometimes', 'boolean'],
        ]);

        $ruta = $request->file('archivo')->storeAs('importaciones', 'clasificados.csv');
        $rutaAbsoluta = Storage::disk('local')->path($ruta); // el disco local vive en storage/app/private

        $codigo = Artisan::call('torneo:importar', [
            'ruta' => $rutaAbsoluta,
            '--fresh' => $request->boolean('fresh'),
        ]);

        $salida = Artisan::output();

        return back()->with([
            'importar_ok' => $codigo === 0,
            'importar_salida' => $salida,
        ]);
    }
}
