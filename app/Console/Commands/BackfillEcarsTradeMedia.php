<?php

namespace App\Console\Commands;

use App\DataTransferObjects\EcarsTradeListingData;
use App\Models\ExternalListing;
use App\Services\EcarsTrade\Contracts\EcarsTradeConnectorInterface;
use App\Services\Imports\EcarsTrade\EcarsTradeListingMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillEcarsTradeMedia extends Command
{
    protected $signature = 'ecarstrade:backfill-media
        {--limit=500 : Nombre max d annonces a traiter}
        {--fetch-missing : Tente de recuperer les images depuis la page detail si aucune image n est en payload}';

    protected $description = 'Complete les images manquantes des annonces eCarsTrade deja importees';

    public function handle(EcarsTradeListingMapper $mapper, EcarsTradeConnectorInterface $connector): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $fetchMissing = (bool) $this->option('fetch-missing');

        $query = ExternalListing::query()
            ->whereIn('status', [ExternalListing::STATUS_PUBLISHED, ExternalListing::STATUS_EXPIRED])
            ->where(function ($q): void {
                $q->whereNull('images')
                    ->orWhere('images', '[]')
                    ->orWhere('images', 'null');
            })
            ->orderByDesc('id')
            ->limit($limit);

        $candidates = $query->get();
        $updated = 0;
        $fromPayload = 0;
        $fromRemote = 0;

        if ($candidates->isEmpty()) {
            $this->info('Aucune annonce sans image a traiter.');
            return self::SUCCESS;
        }

        if ($fetchMissing) {
            try {
                $connector->authenticate();
            } catch (\Throwable $exception) {
                $this->warn('Auth eCarsTrade indisponible pour le fetch distant: ' . $exception->getMessage());
                $fetchMissing = false;
            }
        }

        foreach ($candidates as $listing) {
            $payload = is_array($listing->source_payload) ? $listing->source_payload : [];
            $dto = EcarsTradeListingData::fromArray([
                'source_ref' => $listing->external_id,
                'url' => (string) ($listing->listing_url ?? ''),
                'title' => $listing->title,
                'make' => $listing->make,
                'model' => $listing->model,
                'price' => $listing->price_amount,
                'year' => $listing->year,
                'fuel' => $listing->fuel,
                'gearbox' => $listing->transmission,
                'mileage' => $listing->mileage,
                'color' => $listing->color,
                'raw' => $payload,
            ]);

            $mapped = $mapper->map($dto);
            $images = is_array($mapped['images'] ?? null) ? $mapped['images'] : [];
            $source = 'payload';

            if ($images === [] && $fetchMissing && !empty($listing->listing_url)) {
                try {
                    $details = $connector->fetchListingDetails($dto);
                    $images = is_array($details['images'] ?? null) ? $details['images'] : [];
                    if ($images !== []) {
                        $payload['details'] = $details;
                        $source = 'remote';
                    }
                } catch (\Throwable $exception) {
                    Log::warning('eCarsTrade media backfill remote fetch failed', [
                        'external_listing_id' => $listing->id,
                        'external_id' => $listing->external_id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            if ($images === []) {
                continue;
            }

            $listing->update([
                'images' => array_values(array_unique($images)),
                'source_payload' => $payload,
            ]);

            $updated++;
            if ($source === 'remote') {
                $fromRemote++;
            } else {
                $fromPayload++;
            }
        }

        $this->info("Annonces mises a jour: {$updated}");
        $this->info("Images recuperees depuis payload: {$fromPayload}");
        $this->info("Images recuperees depuis page detail: {$fromRemote}");

        return self::SUCCESS;
    }
}

