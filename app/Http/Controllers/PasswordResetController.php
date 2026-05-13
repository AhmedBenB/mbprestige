<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestPasswordResetLinkRequest;
use App\Http\Requests\ResetAccountPasswordRequest;
use App\Models\User;
use App\Notifications\AccountPasswordResetNotification;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function requestClientLink(
        RequestPasswordResetLinkRequest $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        return $this->sendResetLink($request->validated('email'), User::ROLE_CLIENT, $auditLogService, $request);
    }

    public function requestAdminLink(
        RequestPasswordResetLinkRequest $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        return $this->sendResetLink($request->validated('email'), User::ROLE_ADMIN, $auditLogService, $request);
    }

    public function resetClientPassword(
        ResetAccountPasswordRequest $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        return $this->resetPassword(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
            User::ROLE_CLIENT,
            $auditLogService,
            $request,
        );
    }

    public function resetAdminPassword(
        ResetAccountPasswordRequest $request,
        AuditLogService $auditLogService,
    ): JsonResponse
    {
        return $this->resetPassword(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
            User::ROLE_ADMIN,
            $auditLogService,
            $request,
        );
    }

    private function sendResetLink(
        string $email,
        string $context,
        AuditLogService $auditLogService,
        RequestPasswordResetLinkRequest $request,
    ): JsonResponse
    {
        $user = User::query()
            ->where('email', $email)
            ->first();

        $payload = [];

        if ($user && $user->is_active && $this->matchesContext($user, $context)) {
            $token = Password::broker('users')->createToken($user);
            $resetUrl = $this->buildResetUrl($context, $email, $token);

            $user->notify(new AccountPasswordResetNotification($context, $resetUrl));

            if (config('app.debug')) {
                $payload['debug_reset_url'] = $resetUrl;
            }
        }

        $auditLogService->record(
            $context === User::ROLE_ADMIN ? 'admin.password_reset.requested' : 'client.password_reset.requested',
            $user,
            [
                'email' => $email,
                'organization_id' => $user?->organization_id,
                'token_prepared' => $user && $user->is_active && $this->matchesContext($user, $context),
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => 'Si un compte correspondant existe, un lien de reinitialisation a ete prepare.',
            'data' => $payload,
        ]);
    }

    private function resetPassword(
        string $email,
        string $token,
        string $password,
        string $context,
        AuditLogService $auditLogService,
        ResetAccountPasswordRequest $request,
    ): JsonResponse
    {
        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user || ! $user->is_active || ! $this->matchesContext($user, $context)) {
            return response()->json([
                'message' => 'Le lien de reinitialisation est invalide ou expire.',
            ], 422);
        }

        $status = Password::broker('users')->reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $auditLogService->record(
                $context === User::ROLE_ADMIN ? 'admin.password_reset.failed' : 'client.password_reset.failed',
                $user,
                [
                    'email' => $email,
                    'organization_id' => $user?->organization_id,
                ],
                $user,
                request: $request,
            );

            return response()->json([
                'message' => 'Le lien de reinitialisation est invalide ou expire.',
            ], 422);
        }

        $auditLogService->record(
            $context === User::ROLE_ADMIN ? 'admin.password_reset.completed' : 'client.password_reset.completed',
            $user,
            [
                'email' => $email,
                'organization_id' => $user?->organization_id,
            ],
            $user,
            request: $request,
        );

        return response()->json([
            'message' => 'Votre mot de passe a bien ete reinitialise.',
        ]);
    }

    private function matchesContext(User $user, string $context): bool
    {
        if ($context === User::ROLE_ADMIN) {
            return $user->isAdmin();
        }

        return $user->isClient();
    }

    private function buildResetUrl(string $context, string $email, string $token): string
    {
        $frontendUrl = $context === User::ROLE_ADMIN
            ? config('frontend.admin_login_url')
            : config('frontend.client_login_url');

        $baseUrl = $this->qualifyFrontendUrl($frontendUrl);
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl
            . $separator
            . http_build_query([
                'mode' => 'reset',
                'email' => $email,
                'token' => $token,
            ]);
    }

    private function qualifyFrontendUrl(?string $url): string
    {
        $trimmed = trim((string) $url);
        $appUrl = rtrim((string) config('app.url', url('/')), '/');

        if ($trimmed === '') {
            $trimmed = 'connexion_client_sourcing.html';
        }

        if (Str::startsWith($trimmed, ['http://', 'https://'])) {
            return $trimmed;
        }

        return $appUrl . '/' . ltrim($trimmed, '/');
    }
}
