<?php

namespace App\Console\Commands;

use App\Models\SourceImport;
use App\Services\Imports\EcarsTrade\EcarsTradeImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncEcarsTradeListings extends Command
{
    protected $signature = 'ecarstrade:sync
        {--limit=20 : Nombre maximal d\'annonces a importer}
        {--publish : Publier automatiquement les annonces ready_for_review}';
    protected $description = 'Synchronise les annonces eCarsTrade (import brut + normalisation + estimation + similarites)';

    public function handle(EcarsTradeImporter $importer): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $publish = (bool) $this->option('publish');
        $this->info("Demarrage import eCarsTrade (limit={$limit}, auto_publish=" . ($publish ? 'on' : 'off') . ')...');

        $import = $importer->run(triggeredBy: null, limit: $limit, autoPublish: $publish);

        $this->line('Import ID: ' . $import->id);
        $this->line('Statut: ' . $import->status);
        $this->line('Trouvees: ' . $import->fetched_count);
        $this->line('Creees: ' . $import->created_count);
        $this->line('Mises a jour: ' . $import->updated_count);
        $this->line('Erreurs: ' . $import->error_count);

        $this->alertIfZeroResultsRepeated($import->source_id, (int) $import->fetched_count);

        if ($import->status !== 'completed') {
            $this->warn('Import non complet. Verifie les logs.');
            return self::FAILURE;
        }

        $this->info('Import eCarsTrade termine.');

        return self::SUCCESS;
    }

    private function alertIfZeroResultsRepeated(int $sourceId, int $fetchedCount): void
    {
        if ($sourceId <= 0 || $fetchedCount !== 0) {
            return;
        }

        $threshold = max(2, (int) config('ecarstrade.import.zero_results_alert_threshold', 3));
        $cooldownMinutes = max(1, (int) config('ecarstrade.import.zero_results_alert_cooldown_minutes', 60));

        $recent = SourceImport::query()
            ->where('source_id', $sourceId)
            ->where('status', SourceImport::STATUS_COMPLETED)
            ->latest('id')
            ->limit($threshold)
            ->get(['id', 'fetched_count']);

        if ($recent->count() < $threshold) {
            return;
        }

        $allZero = $recent->every(static fn (SourceImport $row): bool => (int) $row->fetched_count === 0);
        if (!$allZero) {
            return;
        }

        $cooldownKey = "ecarstrade:zero-streak-alert:source:{$sourceId}";
        if (!Cache::add($cooldownKey, now()->toIso8601String(), now()->addMinutes($cooldownMinutes))) {
            return;
        }

        $message = "eCarsTrade import returned 0 listings for {$threshold} consecutive cycles.";

        Log::alert($message, [
            'source_id' => $sourceId,
            'threshold' => $threshold,
            'cooldown_minutes' => $cooldownMinutes,
            'recent_import_ids' => $recent->pluck('id')->all(),
        ]);

        $this->warn($message);
    }
}
