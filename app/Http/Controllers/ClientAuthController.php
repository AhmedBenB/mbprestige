<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClaimClientAccountRequest;
use App\Http\Requests\ClientRegisterRequest;
use App\Http\Requests\ClientLoginRequest;
use App\Http\Requests\UpdateClientPasswordRequest;
use App\Http\Requests\UpdateClientProfileRequest;
use App\Models\CustomerSearch;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ClientAccountService;
use App\Services\ClientEmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientAuthController extends Controller
{
    public function register(
        ClientRegisterRequest $request,
        ClientEmailVerificationService $clientEmailVerificationService,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        $existingUser = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if ($existingUser?->isAdmin()) {
            return response()->json([
                'message' => 'Un compte existe deja avec cet email.',
            ], 422);
        }

        if ($existingUser && $existingUser->email_verified_at !== null) {
            return response()->json([
                'message' => 'Un compte existe deja avec cet email. Utilisez plutot la connexion.',
            ], 422);
        }

        $user = DB::transaction(function () use ($existingUser, $request): User {
            $user = $existingUser ?? new User([
                'email' => $request->validated('email'),
                'role' => User::ROLE_CLIENT,
                'is_active' => true,
            ]);

            $user->forceFill([
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'name' => trim($request->validated('first_name') . ' ' . $request->validated('last_name')),
                'phone' => $request->validated('phone'),
                'password' => $request->validated('password'),
                'role' => User::ROLE_CLIENT,
                'is_active' => true,
                'email_verified_at' => null,
            ])->save();

            return $user->fresh(['organization']);
        });

        $verificationMailSent = ! $user->hasVerifiedEmail()
            ? $clientEmailVerificationService->send($user, $existingUser ? 'register_existing' : 'register', $request)
            : true;

        $token = $user->createToken('client-token')->plainTextToken;

        $auditLogService->record(
            $existingUser ? 'client.account.claimed_via_register' : 'client.account.created',
            $user,
            [
                'organization_id' => $user->organization_id,
                'used_association_code' => false,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => $verificationMailSent
                ? 'Compte client créé. Vérifiez maintenant votre email pour accéder à votre espace.'
                : 'Compte client créé, mais l’email de vérification n’a pas pu être envoyé pour le moment. Vous pourrez le renvoyer depuis votre espace client.',
            'data' => [
                'token' => $token,
                'user' => $this->userResource($user),
                'email_verification_sent' => $verificationMailSent,
            ],
        ], 201);
    }

    public function claim(
        ClaimClientAccountRequest $request,
        ClientEmailVerificationService $clientEmailVerificationService,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        $search = CustomerSearch::query()
            ->where('manage_token', $request->validated('manage_token'))
            ->firstOrFail();

        $email = Str::lower($request->validated('email'));

        if (Str::lower((string) $search->client_email) !== $email) {
            return response()->json([
                'message' => 'Les informations fournies ne correspondent pas a cette demande.',
            ], 422);
        }

        $user = $search->clientAccount
            ?? app(ClientAccountService::class)->resolveOrCreate(
                $search->client_email,
                $search->client_first_name,
                $search->client_last_name,
                $search->client_phone,
            );

        if (! $user || $user->isAdmin()) {
            return response()->json([
                'message' => 'Impossible d activer ce compte client.',
            ], 422);
        }

        $user->forceFill([
            'password' => $request->validated('password'),
            'email_verified_at' => $user->email_verified_at,
            'is_active' => true,
            'role' => User::ROLE_CLIENT,
        ])->save();

        if ($search->user_id !== $user->id) {
            $search->update([
                'user_id' => $user->id,
            ]);
        }

        $verificationMailSent = ! $user->hasVerifiedEmail()
            ? $clientEmailVerificationService->send($user, 'claim', $request)
            : true;

        $token = $user->createToken('client-token')->plainTextToken;

        $auditLogService->record(
            'client.account.claimed',
            $user,
            [
                'search_id' => $search->id,
                'organization_id' => $user->organization_id,
            ],
            $search,
            request: $request,
        );

        return response()->json([
            'message' => $user->hasVerifiedEmail()
                ? 'Compte client activé.'
                : ($verificationMailSent
                    ? 'Compte client activé. Vérifiez maintenant votre email pour accéder à votre espace.'
                    : 'Compte client activé, mais l’email de vérification n’a pas pu être envoyé pour le moment. Vous pourrez le renvoyer depuis votre espace client.'),
            'data' => [
                'token' => $token,
                'user' => $this->userResource($user),
                'email_verification_sent' => $verificationMailSent,
            ],
        ]);
    }

    public function login(
        ClientLoginRequest $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if (
            ! $user
            || $user->isAdmin()
            || ! $user->is_active
            || ! Hash::check($request->validated('password'), (string) $user->password)
        ) {
            $auditLogService->record(
                'client.login.failed',
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

        $token = $user->createToken('client-token')->plainTextToken;

        $auditLogService->record(
            'client.login.succeeded',
            $user,
            [
                'organization_id' => $user->organization_id,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => $user->hasVerifiedEmail()
                ? 'Connexion reussie.'
                : 'Connexion reussie. Verifiez maintenant votre email pour acceder a votre espace client.',
            'data' => [
                'token' => $token,
                'user' => $this->userResource($user),
            ],
        ]);
    }

    public function attachOrganizationCode(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Le rattachement manuel a un garage n est plus disponible. Les demandes client sont maintenant centralisees par MBPRESTIGE.',
        ], 410);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $this->userResource($user),
                'searches_count' => $user->customerSearches()->whereNull('parent_search_id')->count(),
                // Kept truthy on purpose so any legacy client UI still in cache
                // does not try to reopen the old garage-association onboarding.
                'organization_attached' => true,
                'pending_association_request' => null,
            ],
        ]);
    }

    public function updateProfile(
        UpdateClientProfileRequest $request,
        ClientEmailVerificationService $clientEmailVerificationService,
        AuditLogService $auditLogService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $payload = $request->validated();
        $fullName = trim($payload['first_name'] . ' ' . $payload['last_name']);
        $emailChanged = $user->email !== $payload['email'];

        DB::transaction(function () use ($user, $payload, $fullName): void {
            $user->forceFill([
                'first_name' => $payload['first_name'],
                'last_name' => $payload['last_name'],
                'name' => $fullName,
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'email_verified_at' => $user->email === $payload['email'] ? $user->email_verified_at : null,
            ])->save();

            CustomerSearch::query()
                ->where('user_id', $user->id)
                ->update([
                    'client_name' => $fullName,
                    'client_first_name' => $payload['first_name'],
                    'client_last_name' => $payload['last_name'],
                    'client_email' => $payload['email'],
                    'client_phone' => $payload['phone'],
                ]);
        });

        $user->refresh()->load('organization');

        $verificationMailSent = true;

        if ($emailChanged && ! $user->hasVerifiedEmail()) {
            $verificationMailSent = $clientEmailVerificationService->send($user, 'profile_email_changed', $request);
        }

        $auditLogService->record(
            'client.profile.updated',
            $user,
            [
                'organization_id' => $user->organization_id,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => $emailChanged
                ? ($verificationMailSent
                    ? 'Votre profil a bien été mis à jour. Vérifiez maintenant votre nouvelle adresse email pour continuer à utiliser votre espace client.'
                    : 'Votre profil a bien été mis à jour, mais le mail de vérification n’a pas pu être envoyé pour le moment.')
                : 'Votre profil client a bien ete mis a jour.',
            'data' => [
                'user' => $this->userResource($user),
                'email_verification_sent' => $verificationMailSent,
            ],
        ]);
    }

    public function updatePassword(
        UpdateClientPasswordRequest $request,
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
            'client.password.updated',
            $user,
            [
                'organization_id' => $user->organization_id,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => 'Votre mot de passe a bien ete mis a jour.',
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
            'client.logout',
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

    private function userResource(User $user): array
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
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
            'organization_id' => $user->organization_id,
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'location' => $user->organization->location,
                'description' => $user->organization->description,
            ] : null,
            'needs_partner_code' => false,
        ];
    }

}

