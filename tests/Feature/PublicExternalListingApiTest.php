<?php

namespace Tests\Feature;

use App\Models\ExternalListing;
use App\Models\ListingDocument;
use App\Models\ListingPriceEstimate;
use App\Models\ListingSimilarity;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExternalListingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listing_api_returns_full_payload(): void
    {
        $source = Source::query()->create([
            'code' => 'ecarstrade',
            'name' => 'eCarsTrade',
            'type' => 'marketplace',
            'base_url' => 'https://ecarstrade.com',
            'is_active' => true,
        ]);

        $listing = ExternalListing::query()->create([
            'source_id' => $source->id,
            'external_id' => 'ECT-001',
            'title' => 'BMW 320d 2019',
            'slug' => 'bmw-320d-2019-ect-001',
            'listing_type' => 'auction_open',
            'status' => ExternalListing::STATUS_READY_FOR_REVIEW,
            'price_visible' => false,
            'make' => 'BMW',
            'model' => '320d',
            'year' => 2019,
            'mileage' => 98000,
            'fuel' => 'diesel',
            'transmission' => 'automatic',
            'country' => 'FR',
            'images' => ['https://cdn.example.com/a.jpg'],
            'technical_data' => ['vin' => '123'],
            'equipment' => ['GPS'],
            'source_payload' => ['history_summary' => 'RAS'],
            'last_seen_at' => now(),
        ]);

        ListingPriceEstimate::query()->create([
            'external_listing_id' => $listing->id,
            'estimated_price_min' => 18500,
            'estimated_price_max' => 21000,
            'estimated_price_confidence' => 0.7,
            'confidence_label' => 'medium',
            'estimated_price_reason' => '7 annonces similaires',
            'sample_size' => 7,
        ]);

        ListingDocument::query()->create([
            'external_listing_id' => $listing->id,
            'document_type' => 'report',
            'title' => 'Rapport expert',
            'file_url' => 'https://cdn.example.com/report.pdf',
            'is_published' => true,
        ]);

        $similar = ExternalListing::query()->create([
            'source_id' => $source->id,
            'external_id' => 'ECT-002',
            'title' => 'BMW 320d 2018',
            'slug' => 'bmw-320d-2018-ect-002',
            'listing_type' => 'fixed_price',
            'status' => ExternalListing::STATUS_PUBLISHED,
            'price_visible' => true,
            'price_amount' => 19990,
            'make' => 'BMW',
            'model' => '320d',
            'year' => 2018,
        ]);

        ListingSimilarity::query()->create([
            'external_listing_id' => $listing->id,
            'similar_external_listing_id' => $similar->id,
            'score' => 88,
            'score_breakdown' => ['brand_model' => 40],
        ]);

        $this->getJson('/api/public/listings/' . $listing->slug)
            ->assertOk()
            ->assertJsonPath('data.external_id', 'ECT-001')
            ->assertJsonPath('data.price_estimation.sample_size', 7)
            ->assertJsonPath('data.documents.0.title', 'Rapport expert')
            ->assertJsonPath('data.similar_listings.0.id', $similar->id);
    }

    public function test_do_not_publish_listing_is_not_publicly_accessible(): void
    {
        $source = Source::query()->create([
            'code' => 'ecarstrade',
            'name' => 'eCarsTrade',
            'type' => 'marketplace',
            'base_url' => 'https://ecarstrade.com',
            'is_active' => true,
        ]);

        $listing = ExternalListing::query()->create([
            'source_id' => $source->id,
            'external_id' => 'ECT-003',
            'title' => 'Non publiable',
            'slug' => 'non-publiable',
            'status' => ExternalListing::STATUS_DO_NOT_PUBLISH,
        ]);

        $this->get('/vehicules/' . $listing->slug)->assertNotFound();
        $this->getJson('/api/public/listings/' . $listing->slug)->assertNotFound();
    }
}
