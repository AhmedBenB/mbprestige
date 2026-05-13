<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientAccountService
{
    public function resolveOrCreate(
        ?string $email,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $phone = null,
        ?int $organizationId = null,
    ): ?User {
        $normalizedEmail = Str::lower(trim((string) $email));

        if ($normalizedEmail === '') {
            return null;
        }

        $firstName = $this->cleanValue($firstName);
        $lastName = $this->cleanValue($lastName);
        $phone = $this->cleanValue($phone);

        $user = User::query()->firstOrNew([
            'email' => $normalizedEmail,
        ]);

        if (! $user->exists) {
            $user->password = Str::random(40);
            $user->role = User::ROLE_CLIENT;
            $user->is_admin = false;
            $user->is_active = true;
        }

        if (! $user->is_admin) {
            $user->role = User::ROLE_CLIENT;
        }

        if (
            $organizationId !== null
            && $user->organization_id !== null
            && (int) $user->organization_id !== (int) $organizationId
        ) {
            throw ValidationException::withMessages([
                'email' => 'Ce client est deja rattache a un autre partenaire. Merci de contacter le support pour un transfert.',
            ]);
        }

        $user->first_name = $this->preferIncomingValue($firstName, $user->first_name);
        $user->last_name = $this->preferIncomingValue($lastName, $user->last_name);
        $user->phone = $this->preferIncomingValue($phone, $user->phone);

        if ($organizationId !== null) {
            $user->organization_id = $organizationId;
        }

        $displayName = trim(implode(' ', array_filter([
            $user->first_name,
            $user->last_name,
        ])));

        if ($displayName !== '') {
            $user->name = $displayName;
        } elseif (trim((string) $user->name) === '') {
            $user->name = $normalizedEmail;
        }

        $user->save();

        return $user;
    }

    private function cleanValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function preferIncomingValue(?string $incomingValue, ?string $existingValue): ?string
    {
        return $incomingValue !== null ? $incomingValue : $existingValue;
    }
}
