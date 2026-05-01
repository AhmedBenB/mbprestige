<?php

namespace App\Console\Commands;

use App\Jobs\SyncSourceJob;
use App\Models\Source;
use App\Models\SourceImport;
use Illuminate\Console\Command;

class ImportEcarsTradeLatestCommand extends Command
{
    protected $signature = 'ecarstrade:import-latest
        {--base-url= : Base URL API eCarsTrade (ex: https://api.ecarstrade.com)}
        {--endpoint=/vehicles : Endpoint relatif}
        {--auth=bearer : none|bearer|basic|api_key}
        {--token= : Bearer token}
        {--username= : Basic auth username}
        {--password= : Basic auth password}
        {--api-key= : API key}
        {--api-key-header=X-API-Key : Nom du header API key}
        {--api-key-query= : Nom du paramètre query API key}
        {--data-path=data.items : Chemin vers la liste des annonces}
        {--total-path=data.total : Chemin vers le total}
        {--id-field=id : Champ identifiant annonce}
        {--per-page=20 : Taille de page}
        {--limit=20 : Nombre max d\'annonces à importer}
        {--sort=-created_at : Tri API}
        {--margin=8 : Marge appliquée sur les prix}
        {--margin-type=percent : percent|fixed}
        {--auto-approve=1 : 1 pour publier auto après workflow}'
    ;

    protected $description = 'Importe les dernières annonces eCarsTrade (avec marge) dans le catalogue.';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('base-url') ?: env('ECARSTRADE_BASE_URL', ''));
        if ($baseUrl === '') {
            $this->error('Base URL manquante. Utilisez --base-url=... ou ECARSTRADE_BASE_URL dans .env');
            return self::FAILURE;
        }

        $authMode = (string) $this->option('auth');
        $credentials = $this->buildCredentials($authMode);

        $source = Source::updateOrCreate(
            ['name' => 'eCarsTrade API'],
            [
                'type' => 'api',
                'base_url' => rtrim($baseUrl, '/'),
                'auth_mode' => $authMode,
                'credentials_encrypted' => json_encode($credentials, JSON_UNESCAPED_UNICODE),
                'import_frequency_minutes' => 15,
                'is_active' => true,
                'auto_approve' => (bool) ((int) $this->option('auto-approve')),
                'meta' => [
                    'endpoint_path' => (string) $this->option('endpoint'),
                    'pagination_type' => 'page',
                    'page_param' => 'page',
                    'per_page_param' => 'per_page',
                    'per_page' => (int) $this->option('per-page'),
                    'limit_max' => (int) $this->option('limit'),
                    'data_path' => (string) $this->option('data-path'),
                    'total_path' => (string) $this->option('total-path'),
                    'id_field' => (string) $this->option('id-field'),
                    'api_key_header' => (string) $this->option('api-key-header'),
                    'api_key_query' => (string) $this->option('api-key-query'),
                    'extra_query' => [
                        'sort' => (string) $this->option('sort'),
                    ],
                    'margin_type' => (string) $this->option('margin-type'),
                    'margin_value' => (float) $this->option('margin'),
                ],
            ]
        );

        $this->info("Source eCarsTrade prête (ID {$source->id}). Lancement sync...");

        SyncSourceJob::dispatchSync($source->id);

        $import = SourceImport::query()
            ->where('source_id', $source->id)
            ->latest('id')
            ->first();

        if (! $import) {
            $this->error('Aucun import trouvé après sync.');
            return self::FAILURE;
        }

        $this->line("Import #{$import->id} statut: {$import->status}");
        $this->line("Items trouvés: {$import->items_found}");
        $this->line("Items créés: {$import->items_created}");
        $this->line("Items échoués: {$import->items_failed}");

        if ($import->status === 'failed') {
            $this->error('Import en échec: ' . ($import->raw_log ?: 'voir logs'));
            return self::FAILURE;
        }

        if ((int) $import->items_created === 0) {
            $this->warn('Import terminé mais aucune annonce créée. Vérifiez endpoint/data-path/credentials.');
        } else {
            $this->info('Import eCarsTrade terminé.');
        }

        return self::SUCCESS;
    }

    private function buildCredentials(string $authMode): array
    {
        return match ($authMode) {
            'bearer' => [
                'token' => (string) ($this->option('token') ?: env('ECARSTRADE_API_TOKEN', '')),
            ],
            'basic' => [
                'username' => (string) ($this->option('username') ?: env('ECARSTRADE_API_USERNAME', '')),
                'password' => (string) ($this->option('password') ?: env('ECARSTRADE_API_PASSWORD', '')),
            ],
            'api_key' => [
                'api_key' => (string) ($this->option('api-key') ?: env('ECARSTRADE_API_KEY', '')),
            ],
            default => [],
        };
    }
}
