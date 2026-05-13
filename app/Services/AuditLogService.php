<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

class AuditLogService
{
    public function record(
        string $eventType,
        ?User $actor = null,
        array $context = [],
        ?Model $auditable = null,
        ?int $organizationId = null,
        ?Request $request = null,
    ): void {
        try {
            AuditLog::query()->create([
                'user_id' => $actor?->id,
                'organization_id' => $organizationId
                    ?? $this->resolveOrganizationId($actor, $auditable, $context),
                'event_type' => $eventType,
                'auditable_type' => $auditable ? $auditable::class : null,
                'auditable_id' => $auditable?->getKey(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'context' => $context !== [] ? $context : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function resolveOrganizationId(?User $actor, ?Model $auditable, array $context): ?int
    {
        if ($actor?->organization_id) {
            return (int) $actor->organization_id;
        }

        $auditableOrganizationId = data_get($auditable, 'organization_id');

        if ($auditableOrganizationId !== null) {
            return (int) $auditableOrganizationId;
        }

        $contextOrganizationId = data_get($context, 'organization_id');

        return $contextOrganizationId !== null ? (int) $contextOrganizationId : null;
    }
}
