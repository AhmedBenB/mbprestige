<?php

namespace App\Policies;

use App\Models\SearchResult;
use App\Models\User;

class SearchResultPolicy
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

    public function view(User $user, SearchResult $result): bool
    {
        $search = $result->relationLoaded('search') ? $result->search : $result->search()->first();

        if (! $search) {
            return false;
        }

        if ($user->isPartnerAdmin()) {
            return $search->organization_id !== null
                && (int) $search->organization_id === (int) $user->organization_id;
        }

        return $user->isClient()
            && (int) $search->user_id === (int) $user->id
            && in_array($result->match_status, [SearchResult::STATUS_APPROVED, SearchResult::STATUS_SHARED], true);
    }

    public function update(User $user, SearchResult $result): bool
    {
        $search = $result->relationLoaded('search') ? $result->search : $result->search()->first();

        return $user->isPartnerAdmin()
            && $search !== null
            && $search->organization_id !== null
            && (int) $search->organization_id === (int) $user->organization_id;
    }
}
