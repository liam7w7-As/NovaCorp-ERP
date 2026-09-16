<?php

namespace App\Http\Middleware;

use App\Services\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermiso
{
    public function handle(Request $request, Closure $next, string $habilidad): Response
    {
        if (! Permisos::puede($request->user(), $habilidad)) {
            abort(403, 'No tienes permiso para esta acción.');
        }

        return $next($request);
    }
}
