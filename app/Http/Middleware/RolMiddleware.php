<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RolMiddleware
{
    // Uso: middleware('rol:admin')
    public function handle(Request $request, Closure $next, string $rol = 'admin'): Response
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->rol !== $rol) {
            abort(403, 'No tiene permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
