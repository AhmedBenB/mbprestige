<?php

namespace App\Services;

use App\Models\ClientAssociationCode;
use App\Models\ClientAssociationRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientAssociationService
{
    public function attachUserByCode(User $user, string $rawCode): Organization
    {
        $code = $this->normalizeCode($rawCode);

        /** @var ClientAssociationCode|null $associationCode */
        $associationCode = ClientAssociationCode::query()
            ->with('organization')
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $associationCode || ! $associationCode->organization?->is_active) {
            throw ValidationException::withMessages([
                'association_code' => 'Le code d association est invalide.',
            ]);
        }

        if ($associationCode->isExpired()) {
            throw ValidationException::withMessages([
                'association_code' => 'Ce code d association a expire.',
            ]);
        }

        if ($associationCode->isExhausted()) {
            throw ValidationException::withMessages([
                'association_code' => 'Ce code d association a deja ete utilise.',
            ]);
        }

        return DB::transaction(function () use ($user, $associationCode): Organization {
            $organization = $associationCode->organization;

            if ($user->organization_id && (int) $user->organization_id !== (int) $organization->id) {
                throw ValidationException::withMessages([
                    'association_code' => 'Ce compte client est deja rattache a un autre garage.',
                ]);
            }

            if ((int) $user->organization_id !== (int) $organization->id) {
                $user->forceFill([
                    'organization_id' => $organization->id,
                ])->save();

                $user->customerSearches()
                    ->whereNull('organization_id')
                    ->update([
                        'organization_id' => $organization->id,
                    ]);
            }

            $associationCode->increment('use_count');
            $associationCode->forceFill([
                'last_used_at' => now(),
                'is_active' => ! $associationCode->fresh()->isExhausted(),
            ])->save();

            ClientAssociationRequest::query()
                ->where('user_id', $user->id)
                ->where('status', ClientAssociationRequest::STATUS_PENDING)
                ->update([
                    'status' => ClientAssociationRequest::STATUS_REJECTED,
                    'admin_response' => 'Demande cloturee automatiquement apres rattachement par code.',
                    'reviewed_at' => now(),
                ]);

            return $organization;
        });
    }

    public function generateCode(
        Organization $organization,
        ?User $actor = null,
        ?string $label = null,
        ?int $maxUses = 1,
    ): ClientAssociationCode {
        return ClientAssociationCode::query()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $actor?->id,
            'code' => $this->generateUniqueCode('CLI'),
            'label' => $label,
            'max_uses' => $maxUses,
            'use_count' => 0,
            'is_active' => true,
        ]);
    }

    public function submitAssociationRequest(
        User $user,
        Organization $organization,
        ?string $message = null,
    ): ClientAssociationRequest {
        if (! $organization->is_active) {
            throw ValidationException::withMessages([
                'organization_id' => 'Ce garage ne peut pas recevoir de nouvelles demandes pour le moment.',
            ]);
        }

        if ($user->organization_id && (int) $user->organization_id !== (int) $organization->id) {
            throw ValidationException::withMessages([
                'organization_id' => 'Ce compte client est deja rattache a un autre garage.',
            ]);
        }

        if ((int) $user->organization_id === (int) $organization->id) {
            throw ValidationException::withMessages([
                'organization_id' => 'Votre compte est deja rattache a ce garage.',
            ]);
        }

        $pendingRequest = ClientAssociationRequest::query()
            ->where('user_id', $user->id)
            ->where('status', ClientAssociationRequest::STATUS_PENDING)
            ->first();

        if ($pendingRequest) {
            if ((int) $pendingRequest->organization_id === (int) $organization->id) {
                throw ValidationException::withMessages([
                    'organization_id' => 'Une demande est deja en attente pour ce garage.',
                ]);
            }

            throw ValidationException::withMessages([
                'organization_id' => 'Une autre demande d association est deja en attente. Merci d attendre sa reponse.',
            ]);
        }

        return ClientAssociationRequest::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => ClientAssociationRequest::STATUS_PENDING,
            'client_message' => $message,
        ]);
    }

    public function reviewAssociationRequest(
        User $actor,
        ClientAssociationRequest $request,
        string $decision,
        ?string $response = null,
    ): ClientAssociationRequest {
        $decision = strtolower(trim($decision));
        $allowed = [ClientAssociationRequest::STATUS_ACCEPTED, ClientAssociationRequest::STATUS_REJECTED];

        if (! in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages([
                'decision' => 'Decision invalide.',
            ]);
        }

        if ($request->status !== ClientAssociationRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => 'Cette demande a deja ete traitee.',
            ]);
        }

        if ($actor->isPartnerAdmin() && (int) $actor->organization_id !== (int) $request->organization_id) {
            throw ValidationException::withMessages([
                'request' => 'Vous ne pouvez traiter que les demandes de votre garage.',
            ]);
        }

        return DB::transaction(function () use ($actor, $request, $decision, $response): ClientAssociationRequest {
            /** @var User $client */
            $client = $request->client()->lockForUpdate()->firstOrFail();

            if (
                $decision === ClientAssociationRequest::STATUS_ACCEPTED
                && $client->organization_id
                && (int) $client->organization_id !== (int) $request->organization_id
            ) {
                throw ValidationException::withMessages([
                    'request' => 'Ce client est deja rattache a un autre garage.',
                ]);
            }

            if ($decision === ClientAssociationRequest::STATUS_ACCEPTED) {
                $client->forceFill([
                    'organization_id' => $request->organization_id,
                ])->save();

                $client->customerSearches()
                    ->whereNull('organization_id')
                    ->update([
                        'organization_id' => $request->organization_id,
                    ]);

                ClientAssociationRequest::query()
                    ->where('user_id', $client->id)
                    ->where('status', ClientAssociationRequest::STATUS_PENDING)
                    ->where('id', '!=', $request->id)
                    ->update([
                        'status' => ClientAssociationRequest::STATUS_REJECTED,
                        'admin_response' => 'Demande cloturee car une autre association a ete acceptee.',
                        'reviewed_at' => now(),
                        'reviewed_by_user_id' => $actor->id,
                    ]);
            }

            $request->forceFill([
                'status' => $decision,
                'admin_response' => $response,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $actor->id,
            ])->save();

            return $request->fresh(['organization', 'client', 'reviewer']);
        });
    }

    public function normalizeCode(string $rawCode): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($rawCode))) ?: '';
    }

    public function generateUniqueCode(string $prefix): string
    {
        do {
            $candidate = strtoupper($prefix) . random_int(100000, 999999);
        } while (ClientAssociationCode::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
