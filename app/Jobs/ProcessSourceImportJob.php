<?php

namespace App\Jobs;

use App\Models\Source;
use App\Models\SourceImport;
use App\Models\SourceImportItem;
use App\Services\Imports\ImportListingService;
use App\Services\Sources\SourceConnectorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSourceImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $sourceId,
        public readonly int $importId
    ) {}

    public function handle(ImportListingService $importService): void
    {
        $import = SourceImport::find($this->importId);
        $source = Source::find($this->sourceId);

        if (! $import || ! $source) {
            return;
        }

        try {
            // 1) Récupère les annonces brutes de la source et crée les items pending.
            SourceConnectorFactory::make($source)->run($import);

            // Recharger l'import pour récupérer les compteurs mis à jour par le connecteur
            $import->refresh();

            $items = SourceImportItem::query()
                ->where('source_import_id', $import->id)
                ->where('status', 'pending')
                ->get();

            if ((int) $import->items_found === 0) {
                $import->update(['items_found' => $items->count()]);
            }

            foreach ($items as $item) {
                try {
                    $importService->process($item, $source);
                    $import->increment('items_created');
                } catch (\Throwable $e) {
                    $item->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                    $import->increment('items_failed');
                }
            }

            $import->update(['status' => 'done', 'finished_at' => now()]);
            $source->update(['last_sync_at' => now()]);
        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'finished_at' => now(),
                'raw_log' => $e->getMessage(),
            ]);
        }
    }
}
