<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\UpdateAdminProfileRequest;
use App\Http\Requests\UpdateAdminPasswordRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(
        AdminLoginRequest $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if (
            ! $user
            || ! Hash::check($request->validated('password'), (string) $user->password)
            || ! $user->isAdmin()
            || ! $user->is_active
        ) {
            $auditLogService->record(
                'admin.login.failed',
                $user,
                [
                    'email' => $request->validated('email'),
                ],
                $user,
                request: $request,
            );

            return response()->json([
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        if (
            $user->isPartnerAdmin()
            && $user->organization
            && $user->organization->admin_code !== $request->validated('admin_code')
        ) {
            $auditLogService->record(
                'admin.login.failed',
                $user,
                [
                    'email' => $request->validated('email'),
                    'reason' => 'invalid_admin_code',
                    'organization_id' => $user->organization_id,
                ],
                $user,
                request: $request,
            );

            return response()->json([
                'message' => 'Le code administrateur est invalide.',
            ], 401);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        $auditLogService->record(
            'admin.login.succeeded',
            $user,
            [
                'organization_id' => $user->organization_id,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => $this->adminUserResource($user->load('organization')),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->adminUserResource($user->load('organization')),
        ]);
    }

    public function updateProfile(
        UpdateAdminProfileRequest $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone') ?: null,
        ])->save();

        $auditLogService->record(
            'admin.profile.updated',
            $user,
            [
                'organization_id' => $user->organization_id,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => 'Votre profil administrateur a bien ete mis a jour.',
            'data' => $this->adminUserResource($user->fresh('organization')),
        ]);
    }

    public function updatePassword(
        UpdateAdminPasswordRequest $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), (string) $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
            ], 422);
        }

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $auditLogService->record(
            'admin.password.updated',
            $user,
            [
                'organization_id' => $user->organization_id,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => 'Votre mot de passe administrateur a bien ete mis a jour.',
        ]);
    }

    public function logout(
        Request $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        $auditLogService->record(
            'admin.logout',
            $user,
            [
                'organization_id' => $user->organization_id,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => 'Deconnexion reussie.',
        ]);
    }

    private function adminUserResource(User $user): array
    {
        return [
            'id' => $user->id,
            'role' => $user->role,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'organization_id' => $user->organization_id,
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'location' => $user->organization->location,
                'description' => $user->organization->description,
                'slug' => $user->organization->slug,
                'partner_code' => $user->organization->partner_code,
                'admin_code' => $user->organization->admin_code,
                'public_form_path' => 'html/sourcing_auto_accueil_formulaire.html?partner=' . $user->organization->slug,
                'is_active' => (bool) $user->organization->is_active,
            ] : null,
        ];
    }
}
