<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationEcarsTradeAccountRequest;
use App\Models\Organization;
use App\Models\OrganizationSourceAccount;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OrganizationEcarsTradeAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AdminEcarsTradeAccountController extends Controller
{
    public function show(
        Request $request,
        OrganizationEcarsTradeAccountService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $organization = $this->resolveOrganization($user);
        $account = $service->forOrganization($organization);

        return response()->json([
            'data' => $this->resource($organization, $account, $service),
        ]);
    }

    public function update(
        UpdateOrganizationEcarsTradeAccountRequest $request,
        OrganizationEcarsTradeAccountService $service,
        AuditLogService $auditLogService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $organization = $this->requireOrganization($user);

        try {
            $account = $service->storeForOrganization($organization, $request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $auditLogService->record(
            'admin.ecarstrade.credentials.updated',
            $user,
            [
                'organization_id' => $organization->id,
                'source' => OrganizationSourceAccount::SOURCE_ECARSTRADE,
            ],
            $account,
            request: $request,
        );

        return response()->json([
            'message' => 'Les identifiants eCarsTrade du garage ont bien ete enregistres.',
            'data' => $this->resource($organization, $account, $service),
        ]);
    }

    public function test(
        Request $request,
        OrganizationEcarsTradeAccountService $service,
        AuditLogService $auditLogService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $organization = $this->requireOrganization($user);
        $account = $service->forOrganization($organization);

        if (!$account) {
            return response()->json([
                'message' => 'Aucun compte eCarsTrade n est configure pour ce garage.',
            ], 422);
        }

        try {
            $account = $service->testAccount($account);

            $auditLogService->record(
                'admin.ecarstrade.auth_test.succeeded',
                $user,
                [
                    'organization_id' => $organization->id,
                    'source' => OrganizationSourceAccount::SOURCE_ECARSTRADE,
                ],
                $account,
                request: $request,
            );

            return response()->json([
                'message' => 'Connexion eCarsTrade confirmee pour ce garage.',
                'data' => $this->resource($organization, $account, $service),
            ]);
        } catch (Throwable $exception) {
            $freshAccount = $account->fresh();

            $auditLogService->record(
                'admin.ecarstrade.auth_test.failed',
                $user,
                [
                    'organization_id' => $organization->id,
                    'source' => OrganizationSourceAccount::SOURCE_ECARSTRADE,
                    'message' => $exception->getMessage(),
                ],
                $freshAccount,
                request: $request,
            );

            return response()->json([
                'message' => $exception->getMessage(),
                'data' => $this->resource($organization, $freshAccount, $service),
            ], 422);
        }
    }

    private function resolveOrganization(User $user): ?Organization
    {
        if (!$user->organization_id) {
            return null;
        }

        return $user->relationLoaded('organization')
            ? $user->organization
            : $user->organization()->first();
    }

    private function requireOrganization(User $user): Organization
    {
        $organization = $this->resolveOrganization($user);
        abort_unless($organization !== null, 422, 'Cet administrateur n est rattache a aucune organisation.');

        return $organization;
    }

    private function resource(
        ?Organization $organization,
        ?OrganizationSourceAccount $account,
        OrganizationEcarsTradeAccountService $service,
    ): array {
        if (!$organization) {
            return [
                'configurable' => false,
                'configured' => false,
                'organization_id' => null,
                'organization_name' => null,
                'base_url' => $service->normalizeBaseUrl(''),
                'login_email' => null,
                'login_username' => null,
                'has_password' => false,
                'is_active' => false,
                'last_auth_status' => OrganizationSourceAccount::STATUS_NEVER_TESTED,
                'last_auth_status_label' => $service->statusLabel(OrganizationSourceAccount::STATUS_NEVER_TESTED),
                'last_auth_error' => null,
                'last_auth_checked_at' => null,
                'message' => 'La connexion eCarsTrade par garage est disponible uniquement pour un admin rattache a une organisation.',
            ];
        }

        return [
            'configurable' => true,
            'configured' => $account?->hasCredentials() ?? false,
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'base_url' => $service->normalizeBaseUrl((string) ($account?->base_url ?? '')),
            'login_email' => $account?->login_email,
            'login_username' => $account?->login_username,
            'has_password' => $account?->hasCredentials() ?? false,
            'is_active' => (bool) ($account?->is_active ?? false),
            'last_auth_status' => $account?->last_auth_status ?? OrganizationSourceAccount::STATUS_NEVER_TESTED,
            'last_auth_status_label' => $service->statusLabel($account?->last_auth_status),
            'last_auth_error' => $account?->last_auth_error,
            'last_auth_checked_at' => $service->formatCheckedAt($account?->last_auth_checked_at),
            'message' => $account
                ? 'Ces identifiants sont propres a ton garage et seront utilises pour toutes les recherches eCarsTrade de cette organisation.'
                : 'Aucun compte eCarsTrade n est encore configure pour ce garage.',
        ];
    }
}
