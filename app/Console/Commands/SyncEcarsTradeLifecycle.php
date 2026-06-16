<?php

namespace App\Console\Commands;

use App\Models\ExternalListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncEcarsTradeLifecycle extends Command
{
    protected $signature = 'ecarstrade:lifecycle
        {--retention-days=0 : Nombre de jours de retention avant suppression des annonces expirees (0 = immediate)}';

    protected $description = 'Marque les annonces eCarsTrade expirees et purge celles trop anciennes';

    public function handle(): int
    {
        $retentionDays = max(0, (int) $this->option('retention-days'));
        $now = now();

        $expiredByDate = ExternalListing::query()
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->whereNotNull('auction_end_at')
            ->where('auction_end_at', '<=', $now)
            ->update(['status' => ExternalListing::STATUS_EXPIRED]);

        $expiredBySourceStatus = ExternalListing::query()
            ->where('status', ExternalListing::STATUS_PUBLISHED)
            ->whereRaw('LOWER(COALESCE(source_status, "")) IN ("closed","expired","ended","sold")')
            ->update(['status' => ExternalListing::STATUS_EXPIRED]);

        $cutoff = $now->copy()->subDays($retentionDays);
        $purged = ExternalListing::query()
            ->where('status', ExternalListing::STATUS_EXPIRED)
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->whereNotNull('auction_end_at')
                    ->where('auction_end_at', '<=', $cutoff)
                    ->orWhere(function ($q) use ($cutoff): void {
                        $q->whereNull('auction_end_at')
                            ->where('updated_at', '<=', $cutoff);
                    });
            })
            ->delete();

        $this->info("Annonces marquees expirees (date): {$expiredByDate}");
        $this->info("Annonces marquees expirees (source_status): {$expiredBySourceStatus}");
        $this->info("Annonces purgees (> {$retentionDays} jours): {$purged}");

        Log::info('eCarsTrade lifecycle completed', [
            'expired_by_date' => $expiredByDate,
            'expired_by_source_status' => $expiredBySourceStatus,
            'purged' => $purged,
            'retention_days' => $retentionDays,
        ]);

        return self::SUCCESS;
    }
}

