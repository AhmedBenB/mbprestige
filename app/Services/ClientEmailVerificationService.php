<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClientEmailVerificationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function send(User $user, string $trigger, ?Request $request = null): bool
    {
        try {
            $user->sendEmailVerificationNotification();

            $this->auditLogService->record(
                'client.email_verification.sent',
                $user,
                [
                    'trigger' => $trigger,
                    'organization_id' => $user->organization_id,
                ],
                $user,
                request: $request,
            );

            return true;
        } catch (Throwable $exception) {
            Log::error('Impossible d’envoyer l’email de vérification client.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'trigger' => $trigger,
                'organization_id' => $user->organization_id,
                'exception' => $exception->getMessage(),
            ]);

            report($exception);

            $this->auditLogService->record(
                'client.email_verification.failed',
                $user,
                [
                    'trigger' => $trigger,
                    'organization_id' => $user->organization_id,
                    'error' => $exception->getMessage(),
                ],
                $user,
                request: $request,
            );

            return false;
        }
    }
}
