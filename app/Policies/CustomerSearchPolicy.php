<?php

namespace App\Policies;

use App\Models\CustomerSearch;
use App\Models\User;

class CustomerSearchPolicy
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

    public function view(User $user, CustomerSearch $search): bool
    {
        if ($user->isPartnerAdmin()) {
            return $search->organization_id !== null
                && (int) $search->organization_id === (int) $user->organization_id;
        }

        return $user->isClient()
            && $search->parent_search_id === null
            && (int) $search->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isClient();
    }

    public function update(User $user, CustomerSearch $search): bool
    {
        return $this->view($user, $search);
    }

    public function delete(User $user, CustomerSearch $search): bool
    {
        return $user->isPartnerAdmin()
            && $search->organization_id !== null
            && (int) $search->organization_id === (int) $user->organization_id;
    }

    public function run(User $user, CustomerSearch $search): bool
    {
        return $user->isPartnerAdmin()
            && $search->organization_id !== null
            && (int) $search->organization_id === (int) $user->organization_id;
    }

    public function runAll(User $user): bool
    {
        return $user->isAdmin();
    }
}
