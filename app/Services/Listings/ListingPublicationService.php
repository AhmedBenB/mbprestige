<?php

namespace App\Services\Listings;

use App\Models\Listing;
use Illuminate\Validation\ValidationException;

class ListingPublicationService
{
    public function approve(Listing $listing): void
    {
        $this->assertCanApprove($listing);

        $listing->update([
            'publication_status' => 'approved',
        ]);
    }

    public function publish(Listing $listing): void
    {
        $this->assertCanPublish($listing);

        $listing->update([
            'publication_status' => 'published',
            'published_at'       => $listing->published_at ?? now(),
        ]);
    }

    public function pause(Listing $listing): void
    {
        if ($listing->publication_status->value !== 'published') {
            throw ValidationException::withMessages(['listing' => 'Seule une annonce publiée peut être mise en pause.']);
        }
        $listing->update(['publication_status' => 'paused']);
    }

    public function archive(Listing $listing): void
    {
        $listing->update([
            'publication_status' => 'archived',
            'archived_at'        => now(),
        ]);
    }

    private function assertCanApprove(Listing $listing): void
    {
        $allowed = ['imported', 'enriched', 'media_processing', 'ready_for_review'];
        if (! in_array($listing->publication_status->value, $allowed)) {
            throw ValidationException::withMessages(['listing' => 'Statut incompatible avec une approbation.']);
        }

        if (empty($listing->title)) {
            throw ValidationException::withMessages(['title' => 'Le titre est requis.']);
        }

        if (! $listing->vehicle_id) {
            throw ValidationException::withMessages(['vehicle' => 'Un véhicule doit être associé.']);
        }

        if ($listing->isAuction() && ! $listing->starts_at) {
            throw ValidationException::withMessages(['starts_at' => 'La date de début est requise pour une enchère.']);
        }

        if ($listing->isAuction() && ! $listing->ends_at) {
            throw ValidationException::withMessages(['ends_at' => 'La date de fin est requise pour une enchère.']);
        }
    }

    private function assertCanPublish(Listing $listing): void
    {
        if ($listing->publication_status->value !== 'approved') {
            throw ValidationException::withMessages(['listing' => "L'annonce doit être approuvée avant publication."]);
        }

        if ($listing->images()->where('processing_status', 'ready')->count() === 0) {
            throw ValidationException::withMessages(['images' => 'Au moins une image traitée est requise.']);
        }
    }
}
