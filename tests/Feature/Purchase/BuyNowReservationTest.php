<?php

namespace Tests\Feature\Purchase;

use App\Enums\ListingTypeEnum;
use App\Enums\PublicationStatusEnum;
use App\Enums\PurchaseStatusEnum;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesListingFixtures;
use Tests\TestCase;

class BuyNowReservationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesListingFixtures;

    public function test_authenticated_user_can_create_reservation_from_buy_now(): void
    {
        $user = $this->createClientUser();
        $listing = $this->createListing();

        $response = $this->actingAs($user)->post("/app/vehicules/{$listing->slug}/achat-immediat");

        $response->assertRedirect("/app/vehicules/{$listing->slug}/paiement");
        $this->assertDatabaseHas('purchases', [
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'status' => PurchaseStatusEnum::Reserved->value,
        ]);

        $listing->refresh();
        $this->assertSame(PublicationStatusEnum::Reserved, $listing->publication_status);
    }

    public function test_second_user_cannot_reserve_listing_already_reserved(): void
    {
        $listing = $this->createListing();
        $firstUser = $this->createClientUser();
        $secondUser = $this->createClientUser();

        Sanctum::actingAs($firstUser);
        $this->postJson("/api/app/vehicles/{$listing->slug}/buy-now")
            ->assertOk();

        Sanctum::actingAs($secondUser);
        $this->postJson("/api/app/vehicles/{$listing->slug}/buy-now")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['listing']);

        $this->assertSame(1, Purchase::query()->where('listing_id', $listing->id)->count());
    }

    public function test_guest_cannot_access_buy_now_api(): void
    {
        $listing = $this->createListing();

        $response = $this->postJson("/api/app/vehicles/{$listing->slug}/buy-now");

        $response->assertUnauthorized();
    }

    public function test_buy_now_is_refused_for_auction_listing(): void
    {
        $user = $this->createClientUser();
        $listing = $this->createListing([
            'listing_type' => ListingTypeEnum::AuctionOpen,
            'buy_now_price' => 18000,
            'publication_status' => PublicationStatusEnum::Published,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/app/vehicles/{$listing->slug}/buy-now")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['listing']);

        $this->assertDatabaseMissing('purchases', [
            'listing_id' => $listing->id,
            'user_id' => $user->id,
        ]);
    }
}
