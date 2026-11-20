<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza la petición exigiendo uno o varios permisos RBAC (D-01).
 *
 * Uso: `->middleware('permission:employees.view')` o
 * `->middleware('permission:payroll.view,payroll.calculate')` (todos requeridos).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        foreach ($permissions as $permission) {
            abort_unless($user->hasPermission($permission), 403);
        }

        return $next($request);
    }
}
