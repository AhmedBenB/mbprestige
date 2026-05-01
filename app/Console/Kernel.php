<?php

namespace App\Console;

use App\Jobs\ArchiveExpiredListingsJob;
use App\Jobs\ExpireUnpaidPurchasesJob;
use App\Jobs\PublishApprovedListingsJob;
use App\Jobs\RefreshAuctionStatusesJob;
use App\Jobs\ResolveEndedAuctionsJob;
use App\Jobs\SyncSourceJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Import sources actives toutes les 15 minutes
        $schedule->job(new SyncSourceJob())->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        // Rafraîchir les statuts enchères chaque minute
        $schedule->job(new RefreshAuctionStatusesJob())->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        // Résoudre enchères terminées chaque minute
        $schedule->job(new ResolveEndedAuctionsJob())->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        // Expirer les réservations sans acompte (30 min)
        $schedule->job(new ExpireUnpaidPurchasesJob())->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        // Publier les annonces approuvées toutes les 5 min
        $schedule->job(new PublishApprovedListingsJob())->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        // Archivage chaque nuit
        $schedule->job(new ArchiveExpiredListingsJob())->dailyAt('02:00')
            ->onOneServer();

        // Regénérer le sitemap chaque heure
        $schedule->command('sitemap:generate')->hourly()
            ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
