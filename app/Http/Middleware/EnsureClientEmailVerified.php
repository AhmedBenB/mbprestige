<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isClient() || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Email non vérifié. Vérifiez votre boîte mail pour accéder à cet espace.',
            'email_verified' => false,
            'data' => [
                'user' => [
                    'email' => $user->email,
                    'email_verified_at' => null,
                ],
            ],
        ], 403);
    }
}
