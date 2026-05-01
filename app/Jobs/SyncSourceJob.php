<?php
// =====================================================================
// app/Jobs/SyncSourceJob.php
// =====================================================================
namespace App\Jobs;
use App\Models\Source;
use App\Models\SourceImport;
use App\Services\Imports\ImportListingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public readonly ?int $sourceId = null) {}

    public function handle(): void
    {
        $query = Source::query()->where('is_active', true);
        if ($this->sourceId) {
            $query->where('id', $this->sourceId);
        }

        $query->each(function (Source $source) {
            $import = SourceImport::create([
                'source_id'  => $source->id,
                'started_at' => now(),
                'status'     => 'running',
            ]);

            try {
                // Déclencher le parsing selon le type de source
                dispatch(new ProcessSourceImportJob($source->id, $import->id));
            } catch (\Throwable $e) {
                $import->update(['status' => 'failed', 'raw_log' => $e->getMessage()]);
            }
        });
    }
}
