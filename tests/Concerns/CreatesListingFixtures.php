<?php

namespace Tests\Concerns;

use App\Enums\ListingTypeEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Listing;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Str;

trait CreatesListingFixtures
{
    protected function createOrganization(array $overrides = []): Organization
    {
        return Organization::query()->create(array_merge([
            'name' => 'MBPRESTIGE Test',
            'country' => 'FR',
            'status' => 'active',
            'user_tier' => 'trial',
        ], $overrides));
    }

    protected function createClientUser(?Organization $organization = null, array $overrides = []): User
    {
        $organization ??= $this->createOrganization();
        $rand = Str::lower(Str::random(8));

        return User::query()->create(array_merge([
            'name' => 'Client Test',
            'first_name' => 'Client',
            'last_name' => 'Test',
            'email' => "client-{$rand}@test.local",
            'phone' => '0600000000',
            'password' => 'password123',
            'organization_id' => $organization->id,
            'role' => 'client',
            'status' => 'active',
        ], $overrides));
    }

    protected function createListing(array $overrides = []): Listing
    {
        $organization = $overrides['organization'] ?? $this->createOrganization();
        $vehicle = $overrides['vehicle'] ?? Vehicle::query()->create([
            'make' => 'BMW',
            'model' => '320d',
            'version' => 'Edition',
            'first_registration_date' => '2020-01-15',
            'mileage' => 45000,
            'fuel_type' => 'diesel',
            'gearbox' => 'auto',
        ]);

        unset($overrides['organization'], $overrides['vehicle']);

        $defaults = [
            'vehicle_id' => $vehicle->id,
            'organization_id' => $organization->id,
            'listing_type' => ListingTypeEnum::FixedPrice,
            'publication_status' => PublicationStatusEnum::Published,
            'auction_status' => null,
            'title' => 'BMW 320d Test',
            'slug' => 'bmw-320d-'.Str::lower(Str::random(8)),
            'currency' => 'EUR',
            'starting_price' => 10000,
            'buy_now_price' => 15000,
            'current_bid' => 0,
            'minimum_increment' => 100,
            'is_featured' => false,
        ];

        return Listing::query()->create(array_merge($defaults, $overrides));
    }
}
