<?php
// Ajouter ce trait dans app/Models/Listing.php
// use Laravel\Scout\Searchable;
//
// Puis dans la classe Listing :

namespace App\Models\Concerns;

/**
 * Mixin à inclure dans App\Models\Listing via :
 *   use \App\Models\Concerns\ListingSearchable;
 *
 * (ou directement dans Listing.php si vous préférez)
 */
trait ListingSearchable
{
    /**
     * Index Meilisearch : champs indexés et filtrables.
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['vehicle', 'coverImage']);
        $v = $this->vehicle;

        return [
            // IDs
            'id'                       => $this->id,
            'slug'                     => $this->slug,

            // Texte libre
            'title'                    => $this->title,
            'short_description'        => $this->short_description,

            // Type et statut
            'listing_type'             => $this->listing_type->value,
            'publication_status'       => $this->publication_status->value,
            'auction_status'           => $this->auction_status?->value,

            // Prix (champ unifié pour tri)
            'display_price'            => (float) ($this->current_bid ?? $this->buy_now_price ?? $this->starting_price ?? 0),
            'starting_price'           => (float) ($this->starting_price ?? 0),
            'buy_now_price'            => (float) ($this->buy_now_price ?? 0),
            'current_bid'              => (float) ($this->current_bid ?? 0),

            // Flags
            'vat_deductible'           => (bool) $this->vat_deductible,
            'is_featured'              => (bool) $this->is_featured,

            // Timestamps (pour tri Meilisearch, doit être numérique)
            'published_at_timestamp'   => $this->published_at?->timestamp ?? 0,
            'ends_at_timestamp'        => $this->ends_at?->timestamp ?? 0,

            // Véhicule (dénormalisé pour recherche rapide)
            'vehicle_make'             => $v?->make,
            'vehicle_model'            => $v?->model,
            'vehicle_version'          => $v?->version,
            'vehicle_fuel_type'        => $v?->fuel_type,
            'vehicle_gearbox'          => $v?->gearbox,
            'vehicle_body_type'        => $v?->body_type,
            'vehicle_color'            => $v?->color,
            'vehicle_origin_country'   => $v?->origin_country,
            'vehicle_mileage'          => (int) ($v?->mileage ?? 0),
            'vehicle_power_hp'         => (int) ($v?->power_hp ?? 0),
            'vehicle_year'             => $v?->registration_year ?? 0,
            'vehicle_doors'            => (int) ($v?->doors ?? 0),
            'vehicle_seats'            => (int) ($v?->seats ?? 0),

            // Image
            'cover_image_url'          => $this->coverImage?->url(),
        ];
    }

    /**
     * Nom de l'index Meilisearch.
     */
    public function searchableAs(): string
    {
        return config('scout.prefix', '') . 'listings';
    }

    /**
     * N'indexer que les annonces publiées.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->publication_status->value === 'published';
    }
}
