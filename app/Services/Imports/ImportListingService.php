<?php

namespace App\Services\Imports;

use App\Jobs\ProcessListingImagesJob;
use App\Models\Listing;
use App\Models\Source;
use App\Models\SourceImport;
use App\Models\SourceImportItem;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportListingService
{
    /**
     * Traite un item brut provenant d'une source.
     * Crée ou met à jour le véhicule + le listing.
     */
    public function process(SourceImportItem $item, Source $source): Listing
    {
        $payload = $item->raw_payload;

        return DB::transaction(function () use ($payload, $source, $item) {

            // 1. Normaliser les données véhicule
            $vehicleData = $this->normalizeVehicle($payload);

            // 2. Déduplication : VIN > hash technique
            $vehicle = $this->findOrCreateVehicle($vehicleData);

            // 3. Normaliser les données listing
            $listingData = $this->normalizeListing($payload, $vehicle, $source);

            // 4. Déduplication par source_external_id
            $listing = Listing::firstOrNew([
                'source_id'          => $source->id,
                'source_external_id' => $listingData['source_external_id'],
            ]);

            // Ne pas écraser si aucun changement (hash identique)
            $newHash = md5(json_encode($payload));
            if ($listing->exists && $listing->source_payload_hash === $newHash) {
                $item->update(['status' => 'skipped']);
                return $listing;
            }

            $listing->fill($listingData);
            $listing->vehicle_id = $vehicle->id;
            $listing->source_payload_hash = $newHash;
            $listing->last_source_sync_at = now();
            $listing->save();

            // 5. Sauvegarder les images (en file)
            $this->syncImages($listing, $payload['images'] ?? []);

            // 6. Sauvegarder les attributs/options
            $this->syncAttributes($listing, $payload['options'] ?? []);

            // 7. Déclencher traitement médias
            dispatch(new ProcessListingImagesJob($listing->id));

            $item->update(['status' => 'processed']);

            return $listing;
        });
    }

    private function normalizeVehicle(array $payload): array
    {
        return [
            'vin'                    => $payload['vin'] ?? null,
            'make'                   => ucfirst(strtolower($payload['make'] ?? '')),
            'model'                  => $payload['model'] ?? '',
            'version'                => $payload['version'] ?? $payload['trim'] ?? null,
            'body_type'              => $payload['body_type'] ?? $payload['category'] ?? null,
            'fuel_type'              => $payload['fuel'] ?? $payload['fuel_type'] ?? null,
            'gearbox'                => $payload['gearbox'] ?? $payload['transmission'] ?? null,
            'engine_size_cc'         => isset($payload['engine_cc']) ? (int) $payload['engine_cc'] : null,
            'power_hp'               => isset($payload['power_hp']) ? (int) $payload['power_hp'] : null,
            'power_kw'               => isset($payload['power_kw']) ? (int) $payload['power_kw'] : null,
            'co2'                    => isset($payload['co2']) ? (int) $payload['co2'] : null,
            'doors'                  => isset($payload['doors']) ? (int) $payload['doors'] : null,
            'seats'                  => isset($payload['seats']) ? (int) $payload['seats'] : null,
            'color'                  => $payload['color'] ?? null,
            'origin_country'         => strtoupper($payload['country'] ?? $payload['origin_country'] ?? ''),
            'first_registration_date'=> $payload['first_registration'] ?? $payload['registration_date'] ?? null,
            'mileage'                => isset($payload['mileage']) ? (int) $payload['mileage'] : null,
            'emission_class'         => $payload['emission_class'] ?? $payload['euro_norm'] ?? null,
        ];
    }

    private function findOrCreateVehicle(array $data): Vehicle
    {
        // Priorité : VIN si disponible
        if (! empty($data['vin'])) {
            return Vehicle::firstOrCreate(['vin' => $data['vin']], $data);
        }

        // Sinon : hash de déduplication sur les champs techniques stables
        $hash = md5(implode('|', [
            $data['make'], $data['model'], $data['version'],
            $data['first_registration_date'], $data['mileage'], $data['origin_country'],
        ]));

        // On cherche un véhicule existant proche
        $vehicle = Vehicle::query()
            ->where('make', $data['make'])
            ->where('model', $data['model'])
            ->where('first_registration_date', $data['first_registration_date'])
            ->where('mileage', $data['mileage'])
            ->first();

        return $vehicle ?? Vehicle::create($data);
    }

    private function normalizeListing(array $payload, Vehicle $vehicle, Source $source): array
    {
        $title = $this->generateTitle($vehicle, $payload);
        $baseStartingPrice = $payload['starting_price'] ?? $payload['price'] ?? null;
        $baseBuyNowPrice = $payload['buy_now_price'] ?? $payload['price'] ?? null;
        $baseEstimatePrice = $payload['estimate'] ?? null;

        return [
            'source_id'          => $source->id,
            'source_external_id' => (string) ($payload['id'] ?? $payload['external_id'] ?? uniqid()),
            'listing_type'       => $payload['listing_type'] ?? 'fixed_price',
            'publication_status' => $source->auto_approve ? 'approved' : 'imported',
            'title'              => $title,
            'slug'               => $this->generateSlug($title),
            'short_description'  => $payload['description'] ?? null,
            'currency'           => $payload['currency'] ?? 'EUR',
            'starting_price'     => $this->applySourceMargin($baseStartingPrice, $source),
            'buy_now_price'      => $this->applySourceMargin($baseBuyNowPrice, $source),
            'estimate_price'     => $this->applySourceMargin($baseEstimatePrice, $source),
            'minimum_increment'  => $payload['minimum_increment'] ?? 100,
            'starts_at'          => $payload['starts_at'] ?? null,
            'ends_at'            => $payload['ends_at'] ?? null,
            'vat_deductible'     => (bool) ($payload['vat_deductible'] ?? false),
        ];
    }

    private function generateTitle(Vehicle $vehicle, array $payload): string
    {
        $year = $vehicle->registration_year ?? date('Y');
        return "{$vehicle->make} {$vehicle->model} {$vehicle->version} {$year}";
    }

    private function generateSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (Listing::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function syncImages(Listing $listing, array $images): void
    {
        $listing->images()->delete();
        foreach ($images as $i => $url) {
            $listing->images()->create([
                'source_url'        => $url,
                'sort_order'        => $i,
                'processing_status' => 'pending',
                'rights_status'     => 'unknown',
            ]);
        }
    }

    private function syncAttributes(Listing $listing, array $options): void
    {
        $listing->attributes()->delete();
        $groups = ['high_value_options', 'safety_security', 'multimedia', 'other_options'];

        foreach ($options as $group => $items) {
            $groupName = in_array($group, $groups) ? $group : 'other_options';
            $sort = 0;
            foreach ((array) $items as $name => $value) {
                $listing->attributes()->create([
                    'group_name'     => $groupName,
                    'attribute_name' => is_string($name) ? $name : $value,
                    'attribute_value'=> is_string($name) ? $value : null,
                    'sort_order'     => $sort++,
                ]);
            }
        }
    }

    private function applySourceMargin(mixed $price, Source $source): ?float
    {
        if ($price === null || $price === '') {
            return null;
        }

        $base = (float) $price;
        if ($base <= 0) {
            return round($base, 2);
        }

        $meta = $source->meta ?? [];
        $marginType = strtolower((string) ($meta['margin_type'] ?? 'none'));
        $marginValue = (float) ($meta['margin_value'] ?? 0);

        if ($marginValue <= 0 || $marginType === 'none') {
            return round($base, 2);
        }

        $final = match ($marginType) {
            'fixed' => $base + $marginValue,
            'percent', 'percentage' => $base * (1 + ($marginValue / 100)),
            default => $base,
        };

        return round(max(0, $final), 2);
    }
}
