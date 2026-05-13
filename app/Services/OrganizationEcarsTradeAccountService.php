<?php

namespace App\Services;

use App\Models\CustomerSearch;
use App\Models\Organization;
use App\Models\OrganizationSourceAccount;
use App\Services\EcarsTrade\Contracts\EcarsTradeConnectorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OrganizationEcarsTradeAccountService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshotConfig(): array
    {
        return [
            'ecarstrade.base_url' => config('ecarstrade.base_url'),
            'ecarstrade.login_url' => config('ecarstrade.login_url'),
            'ecarstrade.search_url' => config('ecarstrade.search_url'),
            'ecarstrade.future_api_url' => config('ecarstrade.future_api_url'),
            'ecarstrade.email' => config('ecarstrade.email'),
            'ecarstrade.username' => config('ecarstrade.username'),
            'ecarstrade.password' => config('ecarstrade.password'),
            'ecarstrade.auth.probe_url' => config('ecarstrade.auth.probe_url'),
            'ecarstrade.auth.api_url' => config('ecarstrade.auth.api_url'),
            'ecarstrade.auth.refresh_api_url' => config('ecarstrade.auth.refresh_api_url'),
            'ecarstrade.runtime_context' => config('ecarstrade.runtime_context'),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function restoreConfig(array $snapshot): void
    {
        config($snapshot);
    }

    public function forOrganization(?Organization $organization): ?OrganizationSourceAccount
    {
        if (!$organization) {
            return null;
        }

        if ($organization->relationLoaded('ecarsTradeAccount')) {
            return $organization->ecarsTradeAccount;
        }

        return $organization->ecarsTradeAccount()->first();
    }

    public function forOrganizationId(?int $organizationId): ?OrganizationSourceAccount
    {
        if (!$organizationId) {
            return null;
        }

        return OrganizationSourceAccount::query()
            ->where('organization_id', $organizationId)
            ->where('source', OrganizationSourceAccount::SOURCE_ECARSTRADE)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeForOrganization(Organization $organization, array $payload): OrganizationSourceAccount
    {
        $account = $this->forOrganization($organization)
            ?? new OrganizationSourceAccount([
                'organization_id' => $organization->id,
                'source' => OrganizationSourceAccount::SOURCE_ECARSTRADE,
            ]);

        $existingPassword = trim((string) $account->encrypted_password);
        $nextPassword = trim((string) ($payload['password'] ?? ''));
        $loginEmail = trim((string) ($payload['login_email'] ?? ''));
        $loginUsername = trim((string) ($payload['login_username'] ?? ''));
        $baseUrl = $this->normalizeBaseUrl((string) ($payload['base_url'] ?? ''));

        if ($loginEmail === '' && $loginUsername === '') {
            throw new RuntimeException('Merci de renseigner au moins un email ou un identifiant eCarsTrade.');
        }

        if (!$account->exists && $nextPassword === '') {
            throw new RuntimeException('Le mot de passe eCarsTrade est obligatoire lors de la premiere configuration.');
        }

        $account->forceFill([
            'organization_id' => $organization->id,
            'source' => OrganizationSourceAccount::SOURCE_ECARSTRADE,
            'login_email' => $loginEmail !== '' ? $loginEmail : null,
            'login_username' => $loginUsername !== '' ? $loginUsername : null,
            'base_url' => $baseUrl,
            'is_active' => true,
            'last_auth_status' => OrganizationSourceAccount::STATUS_NEVER_TESTED,
            'last_auth_error' => null,
            'last_auth_checked_at' => null,
        ]);

        if ($nextPassword !== '') {
            $account->encrypted_password = $nextPassword;
        } elseif ($existingPassword !== '') {
            $account->encrypted_password = $existingPassword;
        }

        $account->save();

        return $account->fresh();
    }

    public function testAccount(OrganizationSourceAccount $account): OrganizationSourceAccount
    {
        try {
            $this->runWithAccount($account, function (): void {
                app(EcarsTradeConnectorInterface::class)->authenticate();
            });

            $account->forceFill([
                'last_auth_status' => OrganizationSourceAccount::STATUS_OK,
                'last_auth_error' => null,
                'last_auth_checked_at' => now(),
                'is_active' => true,
            ])->save();
        } catch (Throwable $exception) {
            $account->forceFill([
                'last_auth_status' => OrganizationSourceAccount::STATUS_FAILED,
                'last_auth_error' => Str::limit($exception->getMessage(), 1500, ''),
                'last_auth_checked_at' => now(),
            ])->save();

            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }

        return $account->fresh();
    }

    public function readinessMessageForSearch(CustomerSearch $search): ?string
    {
        if (!$search->organization_id) {
            return null;
        }

        $account = $this->forOrganizationId((int) $search->organization_id);
        if (!$account) {
            return 'Le compte eCarsTrade de ce garage n est pas encore configure. Ouvre Mon compte puis renseigne les identifiants eCarsTrade.';
        }

        if (!$account->hasCredentials() || !$account->is_active) {
            return 'Le compte eCarsTrade de ce garage est incomplet ou inactif. Ouvre Mon compte puis verifie les identifiants eCarsTrade.';
        }

        return null;
    }

    /**
     * @template TReturn
     * @param  callable(?OrganizationSourceAccount): TReturn  $callback
     * @return TReturn
     */
    public function runWithSearchCredentials(CustomerSearch $search, callable $callback)
    {
        if (!$search->organization_id) {
            return $callback(null);
        }

        $account = $this->forOrganizationId((int) $search->organization_id);

        if (!$account || !$account->hasCredentials() || !$account->is_active) {
            throw new RuntimeException(
                $this->readinessMessageForSearch($search)
                    ?? 'Le compte eCarsTrade de ce garage n est pas disponible.'
            );
        }

        Log::info('eCarsTrade organization account selected for search', [
            'search_id' => $search->id,
            'organization_id' => $search->organization_id,
            'source_account_id' => $account->id,
            'login' => $this->maskLogin($account->loginIdentifier()),
            'base_url' => $this->normalizeBaseUrl((string) ($account->base_url ?: config('ecarstrade.base_url'))),
            'last_auth_status' => $account->last_auth_status,
            'last_auth_checked_at' => $this->formatCheckedAt($account->last_auth_checked_at),
        ]);

        return $this->runWithAccount($account, $callback);
    }

    /**
     * @template TReturn
     * @param  callable(OrganizationSourceAccount): TReturn  $callback
     * @return TReturn
     */
    public function runWithAccount(OrganizationSourceAccount $account, callable $callback)
    {
        $snapshot = $this->snapshotConfig();
        $this->applyAccountConfig($account);

        try {
            return $callback($account);
        } finally {
            $this->restoreConfig($snapshot);
        }
    }

    public function applyAccountConfig(OrganizationSourceAccount $account): void
    {
        $baseUrl = $this->normalizeBaseUrl((string) ($account->base_url ?: config('ecarstrade.base_url')));

        config([
            'ecarstrade.base_url' => $baseUrl,
            'ecarstrade.login_url' => $baseUrl . '/login',
            'ecarstrade.search_url' => $baseUrl . '/search',
            'ecarstrade.future_api_url' => $baseUrl . '/future_api.php',
            'ecarstrade.email' => $account->login_email,
            'ecarstrade.username' => $account->login_username ?: $account->login_email,
            'ecarstrade.password' => $account->encrypted_password,
            'ecarstrade.auth.probe_url' => $baseUrl . '/search',
            'ecarstrade.auth.api_url' => $baseUrl . '/api/v1/auth/login',
            'ecarstrade.auth.refresh_api_url' => $baseUrl . '/api/v1/auth/refreshToken',
            'ecarstrade.runtime_context' => $this->mergeRuntimeContext([
                'source' => OrganizationSourceAccount::SOURCE_ECARSTRADE,
                'organization_id' => $account->organization_id,
                'source_account_id' => $account->id,
                'source_account_login' => $this->maskLogin($account->loginIdentifier()),
                'source_account_base_url' => $baseUrl,
                'source_account_last_auth_status' => $account->last_auth_status,
                'source_account_is_active' => (bool) $account->is_active,
            ]),
        ]);
    }

    public function normalizeBaseUrl(string $baseUrl): string
    {
        $normalized = trim($baseUrl);

        if ($normalized === '') {
            $normalized = trim((string) config('ecarstrade.base_url', 'https://ecarstrade.com'));
        }

        return rtrim($normalized, '/');
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            OrganizationSourceAccount::STATUS_OK => 'Connexion validee',
            OrganizationSourceAccount::STATUS_FAILED => 'Dernier test en echec',
            default => 'Jamais teste',
        };
    }

    public function formatCheckedAt(?Carbon $checkedAt): ?string
    {
        return $checkedAt?->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function mergeRuntimeContext(array $context): array
    {
        $existing = config('ecarstrade.runtime_context', []);

        return array_merge(
            is_array($existing) ? $existing : [],
            Arr::where($context, static fn ($value) => $value !== null && $value !== '')
        );
    }

    private function maskLogin(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (!str_contains($value, '@')) {
            return Str::limit($value, 2, '') . '***';
        }

        [$local, $domain] = explode('@', $value, 2);
        $prefix = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $prefix . '***@' . $domain;
    }
}
