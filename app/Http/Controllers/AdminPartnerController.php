<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerAdminRequest;
use App\Models\Organization;
use App\Models\OrganizationSourceAccount;
use App\Models\User;
use App\Services\AdminSettingsService;
use App\Services\AuditLogService;
use App\Services\ClientAssociationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminPartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request->user());

        $organizations = Organization::query()
            ->where('is_active', true)
            ->with([
                'adminUsers' => fn ($query) => $query->orderBy('id'),
                'ecarsTradeAccount',
            ])
            ->withCount([
                'adminUsers as admins_count',
                'clientUsers as clients_count',
                'searches as searches_count',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $organizations
                ->map(fn (Organization $organization) => $this->organizationResource($organization))
                ->values(),
        ]);
    }

    public function store(
        StorePartnerAdminRequest $request,
        ClientAssociationService $clientAssociationService,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->ensureSuperAdmin($actor);

        if (User::query()->where('email', $request->validated('admin_email'))->exists()) {
            return response()->json([
                'message' => 'Un compte existe deja avec cet email admin.',
            ], 422);
        }

        $organization = DB::transaction(function () use ($request, $clientAssociationService): Organization {
            $organization = Organization::query()->create([
                'name' => $request->validated('organization_name'),
                'location' => $request->validated('organization_location'),
                'description' => $request->validated('organization_description'),
                'slug' => $this->uniqueSlug($request->validated('organization_slug') ?: $request->validated('organization_name')),
                'partner_code' => $this->generateUniquePartnerCode($request->validated('organization_name')),
                'admin_code' => $this->generateUniqueAdminCode(),
                'is_active' => true,
            ]);

            $admin = User::query()->create([
                'organization_id' => $organization->id,
                'first_name' => $request->validated('admin_first_name'),
                'last_name' => $request->validated('admin_last_name'),
                'name' => trim($request->validated('admin_first_name') . ' ' . $request->validated('admin_last_name')),
                'email' => $request->validated('admin_email'),
                'phone' => $request->validated('admin_phone'),
                'password' => $request->validated('password'),
                'role' => User::ROLE_ADMIN,
                'is_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $clientAssociationService->generateCode(
                $organization,
                $admin,
                'Code client initial',
                1,
            );

            return $organization->fresh([
                'adminUsers',
                'ecarsTradeAccount',
            ]);
        });

        $auditLogService->record(
            'admin.partner.created',
            $actor,
            [
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
            ],
            $organization,
            request: $request,
        );

        return response()->json([
            'message' => 'Admin partenaire cree.',
            'data' => $this->organizationResource($organization),
        ], 201);
    }

    public function destroy(
        Request $request,
        Organization $partner,
        AuditLogService $auditLogService,
        AdminSettingsService $settingsService,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->ensureSuperAdmin($actor);

        DB::transaction(function () use ($partner, $settingsService): void {
            $partner->forceFill([
                'is_active' => false,
            ])->save();

            User::query()
                ->where('organization_id', $partner->id)
                ->where('role', User::ROLE_ADMIN)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            User::query()
                ->where('organization_id', $partner->id)
                ->where('role', User::ROLE_CLIENT)
                ->update([
                    'organization_id' => null,
                    'updated_at' => now(),
                ]);

            OrganizationSourceAccount::query()
                ->where('organization_id', $partner->id)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            $routing = $settingsService->all()['routing']['selected_organization_ids'] ?? [];
            $remaining = collect($routing)
                ->map(static fn ($id) => (int) $id)
                ->reject(static fn ($id) => $id === (int) $partner->id)
                ->values()
                ->all();

            $settingsService->save([
                'routing' => [
                    'selected_organization_ids' => $remaining,
                ],
            ]);
        });

        $auditLogService->record(
            'admin.partner.deleted',
            $actor,
            [
                'organization_id' => $partner->id,
                'organization_name' => $partner->name,
            ],
            $partner,
            request: $request,
        );

        return response()->json([
            'message' => 'Garage supprime de la diffusion et des acces admin.',
        ]);
    }

    private function ensureSuperAdmin(User $user): void
    {
        abort_unless($user->isSuperAdmin(), 403, 'Acces reserve au super-admin.');
    }

    private function organizationResource(Organization $organization): array
    {
        $primaryAdmin = $organization->relationLoaded('adminUsers')
            ? $organization->adminUsers->first()
            : $organization->adminUsers()->first();
        $ecarsTradeAccount = $organization->relationLoaded('ecarsTradeAccount')
            ? $organization->ecarsTradeAccount
            : $organization->ecarsTradeAccount()->first();

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'location' => $organization->location,
            'description' => $organization->description,
            'slug' => $organization->slug,
            'partner_code' => $organization->partner_code,
            'admin_code' => $organization->admin_code,
            'public_form_path' => 'html/sourcing_auto_accueil_formulaire.html?partner=' . $organization->slug,
            'is_active' => (bool) $organization->is_active,
            'stats' => [
                'admins_count' => $organization->admins_count ?? $organization->adminUsers()->count(),
                'clients_count' => $organization->clients_count ?? $organization->clientUsers()->count(),
                'searches_count' => $organization->searches_count ?? $organization->searches()->count(),
            ],
            'ecarstrade' => $this->ecarsTradeResource($ecarsTradeAccount),
            'primary_admin' => $primaryAdmin ? [
                'id' => $primaryAdmin->id,
                'first_name' => $primaryAdmin->first_name,
                'last_name' => $primaryAdmin->last_name,
                'name' => $primaryAdmin->name,
                'email' => $primaryAdmin->email,
                'phone' => $primaryAdmin->phone,
                'is_active' => (bool) $primaryAdmin->is_active,
            ] : null,
        ];
    }

    private function ecarsTradeResource(?OrganizationSourceAccount $account): array
    {
        $configured = $account?->hasCredentials() ?? false;
        $status = !$configured
            ? 'not_configured'
            : ($account?->last_auth_status ?: OrganizationSourceAccount::STATUS_NEVER_TESTED);

        return [
            'configured' => $configured,
            'is_active' => (bool) ($account?->is_active ?? false),
            'status' => $status,
            'status_label' => $this->ecarsTradeStatusLabel($status),
            'last_auth_error' => $account?->last_auth_error,
            'last_auth_checked_at' => $account?->last_auth_checked_at?->toIso8601String(),
        ];
    }

    private function ecarsTradeStatusLabel(string $status): string
    {
        return match ($status) {
            OrganizationSourceAccount::STATUS_OK => 'Connexion validee',
            OrganizationSourceAccount::STATUS_FAILED => 'Dernier test en echec',
            OrganizationSourceAccount::STATUS_NEVER_TESTED => 'Jamais teste',
            default => 'Non configure',
        };
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        $slug = $slug !== '' ? $slug : 'partenaire';
        $candidate = $slug;
        $index = 2;

        while (Organization::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $index;
            $index++;
        }

        return $candidate;
    }

    private function generateUniquePartnerCode(string $label): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($label))), 0, 4));
        $prefix = str_pad($prefix !== '' ? $prefix : 'PART', 4, 'X');

        do {
            $candidate = $prefix . random_int(1000, 9999);
        } while (Organization::query()->where('partner_code', $candidate)->exists());

        return $candidate;
    }

    private function generateUniqueAdminCode(): string
    {
        do {
            $candidate = 'ADM' . random_int(100000, 999999);
        } while (Organization::query()->where('admin_code', $candidate)->exists());

        return $candidate;
    }
}
