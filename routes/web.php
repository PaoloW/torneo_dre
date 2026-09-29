<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\JuezController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// --- Autenticación ---
Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- Raíz: redirige según rol (F19: helper route() en vez de paths escritos a mano) ---
Route::get('/', function () {
    $usuario = Auth::user();

    return redirect()->route($usuario && $usuario->rol === 'admin' ? 'admin.resultados' : 'juez');
})->name('inicio');

// --- Juez (auth) ---
Route::middleware('auth')->group(function () {
    Route::get('/juez', [JuezController::class, 'index'])->name('juez');

    // F19: rutas con nombre (F20: juez.js las recibe con route() desde el layout)
    Route::get('/api/grupos', [GrupoController::class, 'grupos'])->name('api.grupos');
    Route::get('/api/participantes', [GrupoController::class, 'lista'])->name('api.participantes');
    // F3: export PDF (juez y admin) del grupo filtrado
    Route::get('/resultados/pdf', [GrupoController::class, 'pdf'])->name('resultados.pdf');
    Route::post('/api/guardar-orden', [GrupoController::class, 'guardar'])->name('api.guardar-orden');
    Route::post('/api/guardar-observacion', [GrupoController::class, 'observacion'])->name('api.guardar-observacion');
    Route::get('/api/historial/{participante}', [GrupoController::class, 'historial'])->name('api.historial');

    // --- Admin (auth + rol=admin) ---
    Route::middleware('rol:admin')->prefix('admin')->group(function () {
        // F2: misma interfaz del juez, pero en modo solo lectura
        Route::get('/resultados', [JuezController::class, 'resultados'])->name('admin.resultados');
        Route::get('/importar', [ImportController::class, 'mostrarFormulario'])->name('admin.importar');
        Route::post('/importar', [ImportController::class, 'importar'])->name('admin.importar.procesar');
        // F5: gestión de usuarios (crear y editar nombre/contraseña)
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('admin.usuarios');
        Route::post('/usuarios/crear', [UsuarioController::class, 'crear'])->name('admin.usuarios.crear');
        Route::post('/usuarios/{id}/actualizar', [UsuarioController::class, 'actualizar'])->name('admin.usuarios.actualizar');
    });
});
