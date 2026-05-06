<?php

namespace App\Providers;

use App\Models\ClientAssociationRequest;
use App\Models\CustomerSearch;
use App\Models\SearchResult;
use App\Policies\ClientAssociationRequestPolicy;
use App\Policies\CustomerSearchPolicy;
use App\Policies\SearchResultPolicy;
use App\Services\EcarsTrade\Contracts\EcarsTradeConnectorInterface;
use App\Services\EcarsTrade\FakeEcarsTradeConnector;
use App\Services\EcarsTrade\HttpEcarsTradeConnector;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EcarsTradeConnectorInterface::class, function () {
            $connector = strtolower((string) config('ecarstrade.connector', 'fake'));

            return in_array($connector, ['http', 'live', 'real'], true)
                ? new HttpEcarsTradeConnector()
                : new FakeEcarsTradeConnector();
        });
    }

    public function boot(): void
    {
        Gate::policy(CustomerSearch::class, CustomerSearchPolicy::class);
        Gate::policy(SearchResult::class, SearchResultPolicy::class);
        Gate::policy(ClientAssociationRequest::class, ClientAssociationRequestPolicy::class);

        RateLimiter::for('admin-login', function (Request $request) {
            return [
                Limit::perMinute(5)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de tentatives de connexion admin. Merci de reessayer dans une minute.')),
            ];
        });

        RateLimiter::for('client-login', function (Request $request) {
            return [
                Limit::perMinute(5)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de tentatives de connexion client. Merci de reessayer dans une minute.')),
            ];
        });

        RateLimiter::for('client-register', function (Request $request) {
            return [
                Limit::perMinutes(10, 5)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de tentatives de creation de compte. Merci de reessayer dans quelques minutes.')),
            ];
        });

        RateLimiter::for('client-claim', function (Request $request) {
            return [
                Limit::perMinutes(10, 5)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de tentatives d activation de compte. Merci de reessayer dans quelques minutes.')),
            ];
        });

        RateLimiter::for('client-forgot-password', function (Request $request) {
            return [
                Limit::perMinutes(10, 3)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de demandes de reinitialisation client. Merci de reessayer dans quelques minutes.')),
            ];
        });

        RateLimiter::for('admin-forgot-password', function (Request $request) {
            return [
                Limit::perMinutes(10, 3)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de demandes de reinitialisation admin. Merci de reessayer dans quelques minutes.')),
            ];
        });

        RateLimiter::for('client-reset-password', function (Request $request) {
            return [
                Limit::perMinutes(10, 5)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de tentatives de reinitialisation client. Merci de reessayer dans quelques minutes.')),
            ];
        });

        RateLimiter::for('client-email-verification-resend', function (Request $request) {
            return [
                Limit::perMinutes(10, 3)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de renvois d email de verification. Merci de reessayer dans quelques minutes.')),
            ];
        });

        RateLimiter::for('admin-reset-password', function (Request $request) {
            return [
                Limit::perMinutes(10, 5)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de tentatives de reinitialisation admin. Merci de reessayer dans quelques minutes.')),
            ];
        });

        RateLimiter::for('public-request', function (Request $request) {
            return [
                Limit::perMinutes(10, 8)
                    ->by($this->throttleKey($request))
                    ->response(fn () => $this->throttleResponse('Trop de demandes envoyees depuis cette connexion. Merci de reessayer dans quelques minutes.')),
            ];
        });
    }

    private function throttleKey(Request $request): string
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $ip = trim((string) $request->ip());

        return ($email !== '' ? $email : 'guest') . '|' . ($ip !== '' ? $ip : 'unknown');
    }

    private function throttleResponse(string $message)
    {
        return response()->json([
            'message' => $message,
        ], 429);
    }
}
