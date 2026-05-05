<?php

namespace App\Console\Commands;

use App\Services\Imports\EcarsTrade\EcarsTradeImporter;
use Illuminate\Console\Command;

class SyncEcarsTradeListings extends Command
{
    protected $signature = 'ecarstrade:sync {--limit=20 : Nombre maximal d\'annonces a importer}';
    protected $description = 'Synchronise les annonces eCarsTrade (import brut + normalisation + estimation + similarites)';

    public function handle(EcarsTradeImporter $importer): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $this->info("Demarrage import eCarsTrade (limit={$limit})...");

        $import = $importer->run(triggeredBy: null, limit: $limit);

        $this->line('Import ID: ' . $import->id);
        $this->line('Statut: ' . $import->status);
        $this->line('Trouvees: ' . $import->fetched_count);
        $this->line('Creees: ' . $import->created_count);
        $this->line('Mises a jour: ' . $import->updated_count);
        $this->line('Erreurs: ' . $import->error_count);

        if ($import->status !== 'completed') {
            $this->warn('Import non complet. Verifie les logs.');
            return self::FAILURE;
        }

        $this->info('Import eCarsTrade termine.');

        return self::SUCCESS;
    }
}
