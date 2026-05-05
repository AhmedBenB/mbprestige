<?php

namespace App\Jobs;

use App\Services\Imports\EcarsTrade\EcarsTradeImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncEcarsTradeListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $limit = 20,
    ) {
    }

    public function handle(EcarsTradeImporter $importer): void
    {
        $importer->run(triggeredBy: null, limit: $this->limit);
    }
}
