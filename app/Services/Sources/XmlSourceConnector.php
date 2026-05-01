<?php

namespace App\Services\Sources;

use App\Models\Source;
use App\Models\SourceImport;
use App\Models\SourceImportItem;

/**
 * Connecteur XML — parse un flux XML (type RSS/Atom ou export custom).
 *
 * Config source.meta :
 * {
 *   "items_xpath": "//vehicle",     // XPath vers chaque item
 *   "field_map": {                  // mapping noeud XML → champ interne
 *     "VehicleID": "id",
 *     "Brand": "make",
 *     ...
 *   }
 * }
 */
class XmlSourceConnector
{
    public function __construct(private readonly Source $source) {}

    public function run(SourceImport $import): void
    {
        $content = @file_get_contents($this->source->base_url);

        if (! $content) {
            $import->update(['status' => 'failed', 'raw_log' => 'Flux XML inaccessible.']);
            return;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (! $xml) {
            $errors = implode(', ', array_map(fn ($e) => $e->message, libxml_get_errors()));
            $import->update(['status' => 'failed', 'raw_log' => "XML invalide: {$errors}"]);
            libxml_clear_errors();
            return;
        }

        $meta     = $this->source->meta ?? [];
        $xpathStr = $meta['items_xpath'] ?? '//vehicle';
        $fieldMap = $meta['field_map'] ?? [];

        $items = $xml->xpath($xpathStr) ?: [];
        $count = 0;

        foreach ($items as $item) {
            $row = [];

            // Mapper les champs XML définis dans la config
            foreach ($fieldMap as $xmlNode => $internalKey) {
                $val = (string) ($item->$xmlNode ?? '');
                if ($val !== '') $row[$internalKey] = $val;
            }

            // Si pas de mapping défini, convertir tous les noeuds
            if (empty($fieldMap)) {
                foreach ($item->children() as $child) {
                    $row[strtolower($child->getName())] = (string) $child;
                }
                // Attributs XML
                foreach ($item->attributes() as $attrName => $attrVal) {
                    $row[strtolower($attrName)] = (string) $attrVal;
                }
            }

            // Images : chercher un noeud "images" ou "image" avec enfants
            if (isset($item->images)) {
                $imgs = [];
                foreach ($item->images->image ?? [] as $img) {
                    $imgs[] = (string) $img;
                }
                $row['images'] = $imgs ?: [trim((string) $item->images)];
            }

            $externalId = $row['id'] ?? $row['vehicle_id'] ?? md5(json_encode($row));

            SourceImportItem::create([
                'source_import_id' => $import->id,
                'external_id'      => (string) $externalId,
                'status'           => 'pending',
                'raw_payload'      => $row,
            ]);

            $count++;
        }

        $import->update(['items_found' => $count]);
    }
}
