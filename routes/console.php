<?php

use App\DataTransferObjects\SearchCriteriaData;
use App\Models\CustomerSearch;
use App\Services\EcarsTradeSearchService;
use App\Services\EcarsTrade\Contracts\EcarsTradeConnectorInterface;
use App\Services\OrganizationEcarsTradeAccountService;
use App\Services\SearchMatchingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ecarstrade:auth-test {--tail=20 : Nombre de lignes de log a afficher en cas d\'echec}', function () {
    $email = (string) config('ecarstrade.email');
    $password = (string) config('ecarstrade.password');
    $connector = (string) config('ecarstrade.connector', 'unknown');
    $debug = (bool) config('ecarstrade.debug', false);
    $probeUrl = (string) config('ecarstrade.auth.probe_url', '');
    $tail = max(0, (int) $this->option('tail'));

    $maskEmail = static function (string $value): string {
        if ($value === '' || !str_contains($value, '@')) {
            return $value;
        }

        [$local, $domain] = explode('@', $value, 2);
        $prefix = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $prefix . '***@' . $domain;
    };

    $this->newLine();
    $this->components->info('Test d\'authentification eCarsTrade');
    $this->line('Connecteur : ' . $connector);
    $this->line('Email : ' . ($email !== '' ? $maskEmail($email) : '(vide)'));
    $this->line('Probe URL : ' . ($probeUrl !== '' ? $probeUrl : '(vide)'));
    $this->line('Debug logs : ' . ($debug ? 'active' : 'desactive'));

    if ($email === '' || $password === '') {
        $this->newLine();
        $this->components->error('ECARSTRADE_EMAIL ou ECARSTRADE_PASSWORD est vide dans .env.');

        return self::FAILURE;
    }

    try {
        app(EcarsTradeConnectorInterface::class)->authenticate();

        $this->newLine();
        $this->components->info('Connexion eCarsTrade confirmee.');
        $this->comment('Tu peux maintenant relancer une recherche depuis le dashboard admin.');

        return self::SUCCESS;
    } catch (\Throwable $exception) {
        $this->newLine();
        $this->components->error('Authentification eCarsTrade echouee.');
        $this->line($exception->getMessage());

        $logPath = storage_path('logs/laravel.log');
        if ($tail > 0 && is_file($logPath)) {
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES);
            if (is_array($lines) && $lines !== []) {
                $slice = array_slice($lines, -$tail);
                $this->newLine();
                $this->warn("Dernieres lignes du log Laravel ({$tail}) :");
                foreach ($slice as $line) {
                    $this->line($line);
                }
            }
        } else {
            $this->comment('Consulte storage/logs/laravel.log pour le detail.');
        }

        return self::FAILURE;
    }
})->purpose('Teste uniquement l\'authentification eCarsTrade sans passer par le dashboard');

Artisan::command(
    'ecarstrade:search-test
    {make : Marque exacte, ex: BMW}
    {model : Modele exact, ex: 320d}
    {budget : Budget maximal, ex: 20000}
    {year : Annee minimale, ex: 2020}
    {--fuel= : Carburant, ex: diesel}
    {--transmission= : Boite, ex: automatic}
    {--mileage= : Kilometrage maximal}
    {--color= : Couleur}
    {--tolerance=10000 : Tolerance kilometrique}
    {--zone=all_cars : Zone source}
    {--limit=10 : Nombre max de lignes a afficher}
    {--tail=20 : Nombre de lignes de log a afficher en cas d\'echec}
    {--show-raw : Affiche aussi les premiers resultats bruts avant matching}',
    function () {
        $criteria = new SearchCriteriaData(
            make: trim((string) $this->argument('make')),
            model: trim((string) $this->argument('model')),
            budgetMax: (float) $this->argument('budget'),
            yearMin: (int) $this->argument('year'),
            fuel: $this->option('fuel') !== null ? trim((string) $this->option('fuel')) : null,
            transmission: $this->option('transmission') !== null ? trim((string) $this->option('transmission')) : null,
            mileageMax: $this->option('mileage') !== null && $this->option('mileage') !== ''
                ? (int) $this->option('mileage')
                : null,
            mileageTolerance: max(0, (int) $this->option('tolerance')),
            color: $this->option('color') !== null ? trim((string) $this->option('color')) : null,
            sourceZone: trim((string) $this->option('zone')) !== ''
                ? trim((string) $this->option('zone'))
                : 'all_cars',
        );

        $limit = max(1, (int) $this->option('limit'));
        $tail = max(0, (int) $this->option('tail'));
        $showRaw = (bool) $this->option('show-raw');

        $showLogTail = function (int $lines) {
            $logPath = storage_path('logs/laravel.log');
            if ($lines <= 0 || !is_file($logPath)) {
                $this->comment('Consulte storage/logs/laravel.log pour le detail.');

                return;
            }

            $content = @file($logPath, FILE_IGNORE_NEW_LINES);
            if (!is_array($content) || $content === []) {
                $this->comment('Aucune ligne de log disponible.');

                return;
            }

            $slice = array_slice($content, -$lines);
            $this->newLine();
            $this->warn("Dernieres lignes du log Laravel ({$lines}) :");
            foreach ($slice as $line) {
                $this->line($line);
            }
        };

        $this->newLine();
        $this->components->info('Test de recherche eCarsTrade');
        $this->table(
            ['Critere', 'Valeur'],
            array_filter([
                ['Marque', $criteria->make],
                ['Modele', $criteria->model],
                ['Budget max', number_format($criteria->budgetMax, 0, ',', ' ') . ' EUR'],
                ['Annee min', (string) $criteria->yearMin],
                ['Carburant', $criteria->fuel],
                ['Boite', $criteria->transmission],
                ['Km max', $criteria->mileageMax !== null ? number_format($criteria->mileageMax, 0, ',', ' ') : null],
                ['Tolerance km', number_format($criteria->mileageTolerance, 0, ',', ' ')],
                ['Couleur', $criteria->color],
                ['Zone', $criteria->sourceZone],
            ], static fn (array $row) => $row[1] !== null && $row[1] !== '')
        );

        try {
            /** @var EcarsTradeSearchService $searchService */
            $searchService = app(EcarsTradeSearchService::class);
            /** @var SearchMatchingService $matchingService */
            $matchingService = app(SearchMatchingService::class);

            $rawResults = $searchService->search($criteria);
            $matchedResults = $matchingService->filter($rawResults, $criteria);

            $this->newLine();
            $this->components->info('Resume');
            $this->line('Resultats bruts : ' . count($rawResults));
            $this->line('Matches retenus : ' . count($matchedResults));

            if ($showRaw && $rawResults !== []) {
                $rawRows = array_map(function ($item) {
                    return [
                        'Titre' => Str::limit((string) $item->title, 60),
                        'Annee' => $item->year ?? '—',
                        'Prix' => $item->price !== null ? number_format($item->price, 0, ',', ' ') . ' EUR' : '—',
                        'Km' => $item->mileage !== null ? number_format($item->mileage, 0, ',', ' ') : '—',
                        'Fuel' => $item->fuel ?? '—',
                        'Boite' => $item->gearbox ?? '—',
                        'URL' => $item->url,
                    ];
                }, array_slice($rawResults, 0, $limit));

                $this->newLine();
                $this->warn('Premiers resultats bruts');
                $this->table(array_keys($rawRows[0]), $rawRows);
            }

            if ($matchedResults === []) {
                $this->newLine();
                $this->components->warn('Aucun match strict retenu avec les criteres actuels.');

                if (!$showRaw && $rawResults !== []) {
                    $this->comment('Relance avec --show-raw pour voir les annonces brutes renvoyees par eCarsTrade.');
                }

                return self::SUCCESS;
            }

            $matchRows = array_map(function ($item) use ($criteria, $matchingService) {
                return [
                    'Score' => $matchingService->score($item, $criteria),
                    'Titre' => Str::limit((string) $item->title, 60),
                    'Annee' => $item->year ?? '—',
                    'Prix' => $item->price !== null ? number_format($item->price, 0, ',', ' ') . ' EUR' : '—',
                    'Km' => $item->mileage !== null ? number_format($item->mileage, 0, ',', ' ') : '—',
                    'Fuel' => $item->fuel ?? '—',
                    'Boite' => $item->gearbox ?? '—',
                    'URL' => $item->url,
                ];
            }, array_slice($matchedResults, 0, $limit));

            usort($matchRows, static fn (array $a, array $b) => $b['Score'] <=> $a['Score']);

            $this->newLine();
            $this->components->info('Matches retenus');
            $this->table(array_keys($matchRows[0]), $matchRows);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->components->error('Recherche eCarsTrade echouee.');
            $this->line($exception->getMessage());
            $showLogTail($tail);

            return self::FAILURE;
        }
    }
)->purpose('Teste une recherche eCarsTrade complete depuis la console, sans passer par le dashboard');

Artisan::command(
    'ecarstrade:search-test-saved
    {search_id : ID de la recherche sauvegardee a tester}
    {--limit=10 : Nombre max de lignes a afficher}
    {--tail=20 : Nombre de lignes de log a afficher en cas d\'echec}
    {--show-raw : Affiche aussi les premiers resultats bruts avant matching}',
    function () {
        $searchId = (int) $this->argument('search_id');
        $limit = max(1, (int) $this->option('limit'));
        $tail = max(0, (int) $this->option('tail'));
        $showRaw = (bool) $this->option('show-raw');

        $search = CustomerSearch::query()->find($searchId);
        if (!$search) {
            $this->components->error("Recherche {$searchId} introuvable.");

            return self::FAILURE;
        }

        $criteria = SearchCriteriaData::fromModel($search);
        $accountService = app(OrganizationEcarsTradeAccountService::class);
        $account = $accountService->forOrganizationId($search->organization_id);
        $maskedLogin = $account?->loginIdentifier();
        if ($maskedLogin && str_contains($maskedLogin, '@')) {
            [$local, $domain] = explode('@', $maskedLogin, 2);
            $maskedLogin = mb_substr($local, 0, min(2, mb_strlen($local))) . '***@' . $domain;
        } elseif ($maskedLogin) {
            $maskedLogin = Str::limit($maskedLogin, 2, '') . '***';
        }

        $showLogTail = function (int $lines) {
            $logPath = storage_path('logs/laravel.log');
            if ($lines <= 0 || !is_file($logPath)) {
                $this->comment('Consulte storage/logs/laravel.log pour le detail.');

                return;
            }

            $content = @file($logPath, FILE_IGNORE_NEW_LINES);
            if (!is_array($content) || $content === []) {
                $this->comment('Aucune ligne de log disponible.');

                return;
            }

            $slice = array_slice($content, -$lines);
            $this->newLine();
            $this->warn("Dernieres lignes du log Laravel ({$lines}) :");
            foreach ($slice as $line) {
                $this->line($line);
            }
        };

        $this->newLine();
        $this->components->info('Test de recherche eCarsTrade (flux admin sauvegarde)');
        $this->table(
            ['Champ', 'Valeur'],
            array_filter([
                ['Search ID', (string) $search->id],
                ['Organisation', $search->organization_id !== null ? (string) $search->organization_id : '(vide)'],
                ['Compte source', $account ? (string) $account->id : '(aucun)'],
                ['Login source', $maskedLogin ?? '(vide)'],
                ['Base URL', $accountService->normalizeBaseUrl((string) ($account?->base_url ?? ''))],
                ['Marque', $criteria->make],
                ['Modele', $criteria->model],
                ['Budget max', number_format($criteria->budgetMax, 0, ',', ' ') . ' EUR'],
                ['Annee min', (string) $criteria->yearMin],
                ['Zone', $criteria->sourceZone],
            ], static fn (array $row) => $row[1] !== null && $row[1] !== '')
        );

        try {
            /** @var EcarsTradeSearchService $searchService */
            $searchService = app(EcarsTradeSearchService::class);
            /** @var SearchMatchingService $matchingService */
            $matchingService = app(SearchMatchingService::class);

            $rawResults = $searchService->execute($search);
            $matchedResults = $matchingService->filter($rawResults, $criteria);

            $this->newLine();
            $this->components->info('Resume');
            $this->line('Resultats bruts : ' . count($rawResults));
            $this->line('Matches retenus : ' . count($matchedResults));

            if ($showRaw && $rawResults !== []) {
                $rawRows = array_map(function ($item) {
                    return [
                        'Titre' => Str::limit((string) $item->title, 60),
                        'Annee' => $item->year ?? '—',
                        'Prix' => $item->price !== null ? number_format($item->price, 0, ',', ' ') . ' EUR' : '—',
                        'Km' => $item->mileage !== null ? number_format($item->mileage, 0, ',', ' ') : '—',
                        'Fuel' => $item->fuel ?? '—',
                        'Boite' => $item->gearbox ?? '—',
                        'URL' => $item->url,
                    ];
                }, array_slice($rawResults, 0, $limit));

                $this->newLine();
                $this->warn('Premiers resultats bruts');
                $this->table(array_keys($rawRows[0]), $rawRows);
            }

            if ($matchedResults === []) {
                $this->newLine();
                $this->components->warn('Aucun match strict retenu avec cette recherche sauvegardee.');

                if (!$showRaw && $rawResults !== []) {
                    $this->comment('Relance avec --show-raw pour voir les annonces brutes renvoyees par eCarsTrade.');
                }

                return self::SUCCESS;
            }

            $matchRows = array_map(function ($item) use ($criteria, $matchingService) {
                return [
                    'Score' => $matchingService->score($item, $criteria),
                    'Titre' => Str::limit((string) $item->title, 60),
                    'Annee' => $item->year ?? '—',
                    'Prix' => $item->price !== null ? number_format($item->price, 0, ',', ' ') . ' EUR' : '—',
                    'Km' => $item->mileage !== null ? number_format($item->mileage, 0, ',', ' ') : '—',
                    'Fuel' => $item->fuel ?? '—',
                    'Boite' => $item->gearbox ?? '—',
                    'URL' => $item->url,
                ];
            }, array_slice($matchedResults, 0, $limit));

            usort($matchRows, static fn (array $a, array $b) => $b['Score'] <=> $a['Score']);

            $this->newLine();
            $this->components->info('Matches retenus');
            $this->table(array_keys($matchRows[0]), $matchRows);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->components->error('Recherche eCarsTrade sauvegardee echouee.');
            $this->line($exception->getMessage());
            $showLogTail($tail);

            return self::FAILURE;
        }
    }
)->purpose('Teste exactement le flux admin d une recherche sauvegardee via le compte source organisation');

if ((bool) config('ecarstrade.import.enabled', true)) {
    $syncLimit = max(1, (int) config('ecarstrade.import.sync_limit', 20));
    $everyMinutes = max(5, min(59, (int) config('ecarstrade.import.sync_every_minutes', 30)));
    $autoPublish = (bool) config('ecarstrade.import.auto_publish', false);
    $command = 'ecarstrade:sync --limit=' . $syncLimit . ($autoPublish ? ' --publish' : '');

    Schedule::command($command)
        ->cron("*/{$everyMinutes} * * * *")
        ->withoutOverlapping();

    Schedule::command('ecarstrade:lifecycle --retention-days=0')
        ->hourly()
        ->withoutOverlapping();
}
