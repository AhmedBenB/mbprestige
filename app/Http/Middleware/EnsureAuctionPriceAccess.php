<?php

namespace App\Http\Middleware;

use App\Models\Listing;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuctionPriceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $listing = $request->route('listing');

        if (! $listing instanceof Listing || ! $listing->isAuction()) {
            return $next($request);
        }

        $authenticatedUser = $request->user() ?? $request->user('sanctum');

        if ($authenticatedUser) {
            return $next($request);
        }

        Log::warning('Auction pricing access denied', [
            'listing_id' => $listing->id,
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Connectez-vous pour accéder aux prix des enchères.',
                'code' => 'AUTH_REQUIRED_FOR_AUCTION_PRICES',
            ], 401);
        }

        return redirect()
            ->route('register')
            ->with('error', 'Créez un compte pour voir les prix des enchères.');
    }
}
