<?php

namespace App\Policies;

use App\Models\ClientAssociationRequest;
use App\Models\User;

class ClientAssociationRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function view(User $user, ClientAssociationRequest $request): bool
    {
        if ($user->isPartnerAdmin()) {
            return $request->organization_id !== null
                && (int) $request->organization_id === (int) $user->organization_id;
        }

        return $user->isClient()
            && (int) $request->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isClient();
    }

    public function review(User $user, ClientAssociationRequest $request): bool
    {
        return $user->isPartnerAdmin()
            && $request->organization_id !== null
            && (int) $request->organization_id === (int) $user->organization_id;
    }
}
