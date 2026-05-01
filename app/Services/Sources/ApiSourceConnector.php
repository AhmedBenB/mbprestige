<?php

namespace App\Services\Sources;

use App\Models\Source;
use App\Models\SourceImport;
use App\Models\SourceImportItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Connecteur API REST générique.
 *
 * Supporte : Bearer token, API key (header ou query), Basic auth, aucune auth.
 *
 * Config source.meta attendue :
 * {
 *   "endpoint_path": "/vehicles",          // chemin relatif à base_url
 *   "pagination_type": "page|offset|cursor|none",
 *   "page_param": "page",
 *   "per_page_param": "per_page",
 *   "per_page": 100,
 *   "data_path": "data.items",             // dot-notation vers le tableau de résultats
 *   "total_path": "data.total",            // optionnel
 *   "id_field": "vehicle_id",              // champ ID dans la réponse
 *   "api_key_header": "X-API-Key",         // si auth_mode=api_key
 *   "api_key_query": "api_key"             // alternative : dans query string
 * }
 */
class ApiSourceConnector
{
    private array $meta;

    public function __construct(private readonly Source $source)
    {
        $this->meta = $source->meta ?? [];
    }

    public function run(SourceImport $import): void
    {
        try {
            $allItems = $this->fetchAll();

            foreach ($allItems as $item) {
                $externalId = $this->extractId($item);

                SourceImportItem::create([
                    'source_import_id' => $import->id,
                    'external_id'      => $externalId,
                    'status'           => 'pending',
                    'raw_payload'      => $item,
                ]);
            }

            $import->update(['items_found' => count($allItems)]);

        } catch (\Throwable $e) {
            Log::error("ApiSourceConnector error [{$this->source->name}]: " . $e->getMessage());
            $import->update([
                'status'  => 'failed',
                'raw_log' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pagine et récupère tous les items.
     */
    private function fetchAll(): array
    {
        $type = $this->meta['pagination_type'] ?? 'page';
        $all  = [];
        $page = 1;
        $limitMax = (int) ($this->meta['limit_max'] ?? 0);

        do {
            $response = $this->fetchPage($page);
            $items    = $this->extractItems($response);
            $all      = array_merge($all, $items);

            if ($limitMax > 0 && count($all) >= $limitMax) {
                $all = array_slice($all, 0, $limitMax);
                break;
            }

            if ($type === 'none') break;

            $total   = $this->extractTotal($response);
            $perPage = (int) ($this->meta['per_page'] ?? 100);
            $hasMore = $total ? (count($all) < $total) : (count($items) === $perPage);

            $page++;

            // Sécurité : max 500 pages
            if ($page > 500) break;

        } while ($hasMore);

        return $all;
    }

    /**
     * Appel HTTP d'une page.
     */
    private function fetchPage(int $page): array
    {
        $url   = rtrim($this->source->base_url, '/') . '/' . ltrim($this->meta['endpoint_path'] ?? '', '/');
        $creds = json_decode($this->source->credentials_encrypted ?? '{}', true);

        $request = Http::timeout(30);

        $extraHeaders = $this->meta['extra_headers'] ?? [];
        if (is_array($extraHeaders) && ! empty($extraHeaders)) {
            $request = $request->withHeaders($extraHeaders);
        }

        // Authentification
        match ($this->source->auth_mode) {
            'bearer'  => $request = $request->withToken($creds['token'] ?? ''),
            'basic'   => $request = $request->withBasicAuth($creds['username'] ?? '', $creds['password'] ?? ''),
            'api_key' => $request = $request->withHeaders([
                ($this->meta['api_key_header'] ?? 'X-Api-Key') => $creds['api_key'] ?? '',
            ]),
            default   => null,
        };

        // Paramètres de pagination
        $queryParams = $this->meta['extra_query'] ?? [];
        if (! is_array($queryParams)) {
            $queryParams = [];
        }
        if (($this->meta['pagination_type'] ?? 'page') !== 'none') {
            $queryParams[$this->meta['page_param']     ?? 'page']     = $page;
            $queryParams[$this->meta['per_page_param'] ?? 'per_page'] = $this->meta['per_page'] ?? 100;
        }

        // API key dans query string si configuré ainsi
        if ($this->source->auth_mode === 'api_key' && ! empty($this->meta['api_key_query'])) {
            $queryParams[$this->meta['api_key_query']] = $creds['api_key'] ?? '';
        }

        $response = $request->get($url, $queryParams);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} sur {$url}");
        }

        return $response->json() ?? [];
    }

    private function extractItems(array $response): array
    {
        $path = $this->meta['data_path'] ?? null;
        if (! $path) return $response;

        $data = $response;
        foreach (explode('.', $path) as $key) {
            $data = $data[$key] ?? [];
        }
        return is_array($data) ? $data : [];
    }

    private function extractTotal(array $response): ?int
    {
        $path = $this->meta['total_path'] ?? null;
        if (! $path) return null;

        $data = $response;
        foreach (explode('.', $path) as $key) {
            $data = $data[$key] ?? null;
            if ($data === null) return null;
        }
        return (int) $data;
    }

    private function extractId(array $item): string
    {
        $field = $this->meta['id_field'] ?? 'id';
        return (string) ($item[$field] ?? md5(json_encode($item)));
    }
}
