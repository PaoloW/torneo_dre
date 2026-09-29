<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\JuezController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// --- Autenticación ---
Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- Raíz: redirige según rol ---
Route::get('/', function () {
    $usuario = Auth::user();

    return redirect($usuario && $usuario->rol === 'admin' ? '/admin/importar' : '/juez');
});

// --- Juez (auth) ---
Route::middleware('auth')->group(function () {
    Route::get('/juez', [JuezController::class, 'index'])->name('juez');

    Route::get('/api/grupos', [GrupoController::class, 'grupos']);
    Route::get('/api/participantes', [GrupoController::class, 'lista']);
    Route::post('/api/guardar-orden', [GrupoController::class, 'guardar']);
    Route::post('/api/guardar-observacion', [GrupoController::class, 'observacion']);
    Route::get('/api/historial/{participante}', [GrupoController::class, 'historial']);

    // --- Admin (auth + rol=admin) ---
    Route::middleware('rol:admin')->prefix('admin')->group(function () {
        Route::get('/importar', [ImportController::class, 'mostrarFormulario']);
        Route::post('/importar', [ImportController::class, 'importar']);
    });
});
