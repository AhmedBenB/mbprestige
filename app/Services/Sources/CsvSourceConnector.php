<?php

namespace App\Services\Sources;

use App\Models\Source;
use App\Models\SourceImport;
use App\Models\SourceImportItem;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

/**
 * Connecteur CSV — lit un fichier CSV uploadé ou accessible via URL.
 *
 * Format CSV attendu (colonnes flexibles, mapping configurable via source.meta) :
 *   id, make, model, version, fuel, gearbox, year, mileage, price,
 *   country, color, power_hp, body_type, listing_type, images (pipe-séparés), ...
 */
class CsvSourceConnector
{
    // Mapping par défaut colonne CSV → champ interne
    private array $defaultMapping = [
        'id'           => 'id',
        'make'         => 'make',
        'model'        => 'model',
        'version'      => 'version',
        'fuel'         => 'fuel_type',
        'gearbox'      => 'gearbox',
        'year'         => 'year',
        'mileage'      => 'mileage',
        'price'        => 'price',
        'buy_now'      => 'buy_now_price',
        'country'      => 'origin_country',
        'color'        => 'color',
        'power_hp'     => 'power_hp',
        'body_type'    => 'body_type',
        'listing_type' => 'listing_type',
        'vat'          => 'vat_deductible',
        'description'  => 'description',
        'images'       => 'images',
        'starts_at'    => 'starts_at',
        'ends_at'      => 'ends_at',
        'doors'        => 'doors',
        'seats'        => 'seats',
        'co2'          => 'co2',
        'vin'          => 'vin',
    ];

    public function __construct(private readonly Source $source) {}

    /**
     * Point d'entrée principal : récupère le CSV et crée les SourceImportItems.
     */
    public function run(SourceImport $import): void
    {
        $csvContent = $this->fetchContent();

        if (empty($csvContent)) {
            $import->update(['status' => 'failed', 'raw_log' => 'Contenu CSV vide ou inaccessible.']);
            return;
        }

        try {
            $reader = Reader::createFromString($csvContent);
            $reader->setHeaderOffset(0);          // Première ligne = en-têtes
            $reader->setDelimiter($this->delimiter());

            $mapping  = $this->getMapping();
            $count    = 0;
            $failed   = 0;

            foreach ($reader->getRecords() as $row) {
                try {
                    $normalized = $this->normalizeRow($row, $mapping);

                    SourceImportItem::create([
                        'source_import_id' => $import->id,
                        'external_id'      => $normalized['id'] ?? null,
                        'status'           => 'pending',
                        'raw_payload'      => $normalized,
                    ]);
                    $count++;
                } catch (\Throwable $e) {
                    $failed++;
                }
            }

            $import->update([
                'items_found' => $count,
                'items_failed' => $failed,
            ]);

        } catch (\Throwable $e) {
            $import->update(['status' => 'failed', 'raw_log' => $e->getMessage()]);
        }
    }

    /**
     * Récupère le contenu CSV selon la config de la source.
     */
    private function fetchContent(): string
    {
        $meta = $this->source->meta ?? [];

        // Depuis URL distante
        if (! empty($this->source->base_url)) {
            $context = null;

            if ($this->source->auth_mode === 'basic') {
                $creds   = json_decode($this->source->credentials_encrypted ?? '{}', true);
                $context = stream_context_create([
                    'http' => [
                        'header' => 'Authorization: Basic ' . base64_encode(
                            ($creds['username'] ?? '') . ':' . ($creds['password'] ?? '')
                        ),
                    ],
                ]);
            }

            $content = @file_get_contents($this->source->base_url, false, $context);
            return $content ?: '';
        }

        // Depuis un fichier local/storage
        if (! empty($meta['local_path'])) {
            return Storage::get($meta['local_path']) ?? '';
        }

        return '';
    }

    private function delimiter(): string
    {
        return $this->source->meta['delimiter'] ?? ',';
    }

    private function getMapping(): array
    {
        $custom = $this->source->meta['column_mapping'] ?? [];
        return array_merge($this->defaultMapping, $custom);
    }

    private function normalizeRow(array $row, array $mapping): array
    {
        $result = [];

        foreach ($mapping as $csvCol => $internalKey) {
            if (isset($row[$csvCol])) {
                $result[$internalKey] = trim($row[$csvCol]);
            }
        }

        // Conversions spécifiques
        if (isset($result['images'])) {
            $result['images'] = array_filter(explode('|', $result['images']));
        }
        if (isset($result['vat_deductible'])) {
            $result['vat_deductible'] = in_array(strtolower($result['vat_deductible']), ['1', 'true', 'yes', 'oui']);
        }
        if (isset($result['year'])) {
            $result['first_registration'] = $result['year'] . '-01-01';
        }

        // ID externe obligatoire
        if (empty($result['id'])) {
            $result['id'] = md5(json_encode($result));
        }

        return $result;
    }
}
