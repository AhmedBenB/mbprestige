<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;

class OrganizationCodeService
{
    public function __construct(
        private readonly ClientAssociationService $clientAssociationService,
    ) {
    }

    public function attachClientByCode(User $user, string $rawCode): Organization
    {
        return $this->clientAssociationService->attachUserByCode($user, $rawCode);
    }

    public function normalize(string $rawCode): string
    {
        return $this->clientAssociationService->normalizeCode($rawCode);
    }
}
