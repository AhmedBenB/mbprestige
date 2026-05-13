<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Authentification requise.');
        }

        $allowedRoles = array_map(
            static fn (string $role): string => strtolower(trim($role)),
            $roles,
        );

        $userRole = strtolower(trim((string) ($user->role ?: ($user->isAdmin() ? 'admin' : 'client'))));

        if (! in_array($userRole, $allowedRoles, true)) {
            abort(403, 'Acces refuse.');
        }

        return $next($request);
    }
}
