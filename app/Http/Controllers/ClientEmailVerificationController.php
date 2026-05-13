<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ClientEmailVerificationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ClientEmailVerificationController extends Controller
{
    public function notice(Request $request): RedirectResponse
    {
        $params = ['verification' => 'required'];

        if ($request->user()?->email) {
            $params['email'] = $request->user()->email;
        }

        return redirect()->away($this->clientDashboardUrl($params));
    }

    public function verify(
        Request $request,
        AuditLogService $auditLogService,
        int $id,
        string $hash,
    ): RedirectResponse {
        $user = User::query()->find($id);

        if (! $user || ! $user->isClient()) {
            return redirect()->away($this->clientLoginUrl([
                'verification' => 'invalid',
            ]));
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            $auditLogService->record(
                'client.email_verification.invalid',
                $user,
                [
                    'reason' => 'hash_mismatch',
                    'organization_id' => $user->organization_id,
                ],
                $user,
                request: $request,
            );

            return redirect()->away($this->clientLoginUrl([
                'verification' => 'invalid',
                'email' => $user->email,
            ]));
        }

        if (! $request->hasValidSignature()) {
            $auditLogService->record(
                'client.email_verification.expired',
                $user,
                [
                    'organization_id' => $user->organization_id,
                ],
                $user,
                request: $request,
            );

            return redirect()->away($this->clientLoginUrl([
                'verification' => 'expired',
                'email' => $user->email,
            ]));
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->away($this->clientDashboardUrl([
                'verification' => 'already-verified',
                'email' => $user->email,
            ]));
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        $auditLogService->record(
            'client.email_verification.completed',
            $user,
            [
                'organization_id' => $user->organization_id,
            ],
            $user,
            request: $request,
        );

        return redirect()->away($this->clientDashboardUrl([
            'verification' => 'verified',
            'email' => $user->email,
        ]));
    }

    public function resend(
        Request $request,
        ClientEmailVerificationService $clientEmailVerificationService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Votre adresse email est deja verifiee.',
                'data' => [
                    'email_verified' => true,
                    'user' => $this->userResource($user),
                ],
            ]);
        }

        $mailSent = $clientEmailVerificationService->send($user, 'resend', $request);

        if (! $mailSent) {
            return response()->json([
                'message' => 'Impossible d\'envoyer le mail de verification pour le moment. Merci de reessayer dans quelques minutes.',
                'email_verified' => false,
            ], 500);
        }

        return response()->json([
            'message' => 'Un nouvel email de verification vient d\'etre envoye.',
            'data' => [
                'email_verified' => false,
                'user' => $this->userResource($user),
            ],
        ]);
    }

    private function clientLoginUrl(array $params = []): string
    {
        return $this->qualifyFrontendUrl(
            (string) config('frontend.client_login_url', 'connexion_client_sourcing.html'),
            $params,
        );
    }

    private function clientDashboardUrl(array $params = []): string
    {
        return $this->qualifyFrontendUrl(
            (string) config('frontend.client_dashboard_url', 'dashboard_client_sourcing.html'),
            $params,
        );
    }

    private function qualifyFrontendUrl(string $path, array $params = []): string
    {
        $trimmed = trim($path);
        $baseUrl = Str::startsWith($trimmed, ['http://', 'https://'])
            ? $trimmed
            : rtrim(url('/'), '/') . '/' . ltrim($trimmed, '/');

        if ($params === []) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query(array_filter(
            $params,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        ));
    }

    private function userResource(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }
}

