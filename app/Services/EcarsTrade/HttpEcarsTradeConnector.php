<?php

namespace App\Services\EcarsTrade;

use App\DataTransferObjects\EcarsTradeListingData;
use App\DataTransferObjects\SearchCriteriaData;
use App\Services\EcarsTrade\Contracts\EcarsTradeConnectorInterface;
use App\Support\VehicleCatalog;
use DOMDocument;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HttpEcarsTradeConnector implements EcarsTradeConnectorInterface
{
    private CookieJar $cookies;

    private bool $authenticated = false;

    private ?string $accessToken = null;

    private ?string $refreshToken = null;

    private ?string $runtimeAuthApiUrl = null;

    private ?string $runtimeRefreshApiUrl = null;

    private ?int $runtimeAuthCookieLifetime = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $makeMap = null;

    public function __construct(?CookieJar $cookies = null)
    {
        $this->cookies = $cookies ?? new CookieJar();
    }

    public function authenticate(): void
    {
        if ($this->authenticated) {
            return;
        }

        if (!$this->hasCredentialsConfigured()) {
            $this->authenticated = true;

            return;
        }

        $formException = null;
        $apiException = null;
        $loginPage = $this->fetchLoginPage();
        $this->guardSuccessfulResponse($loginPage, 'page de login');
        $this->bootstrapRuntimeAuthConfig($loginPage->body());

        if ($this->prefersApiAuthentication()) {
            $apiException = $this->attemptApiAuthentication();
            if ($apiException === null) {
                $this->authenticated = true;

                return;
            }
        }

        try {
            $csrfToken = $this->extractLoginCsrfToken($loginPage->body());
            $payload = $this->buildLoginPayload($csrfToken);
            $this->logAuthInfo('eCarsTrade login attempt', [
                'login_url' => $this->resolveUrl((string) config('ecarstrade.login_url')),
                'login' => $this->maskEmail($this->resolveLoginIdentifier()),
                'payload_keys' => array_keys($payload),
                'csrf_present' => $csrfToken !== null && $csrfToken !== '',
            ]);
            $response = $this->submitLogin($payload);

            $this->guardSuccessfulLogin($response);
            $this->authenticated = true;

            return;
        } catch (Throwable $exception) {
            $formException = $exception;
            $this->logAuthWarning('eCarsTrade login failed', [
                'message' => $exception->getMessage(),
                'cookies' => $this->exportCookies(),
            ]);
        }

        if ($apiException === null && !$this->prefersApiAuthentication()) {
            $apiException = $this->attemptApiAuthentication();
            if ($apiException === null) {
                $this->authenticated = true;

                return;
            }
        }

        if ($this->requiresAuthentication()) {
            $reasons = [];

            if ($apiException !== null) {
                $reasons[] = 'API auth: ' . $apiException->getMessage();
            }
            if ($formException !== null) {
                $reasons[] = 'HTML auth: ' . $formException->getMessage();
            }

            $message = $reasons !== []
                ? implode(' | ', $reasons)
                : 'verification des identifiants requise';

            throw new RuntimeException(
                'Connexion eCarsTrade impossible: ' . $message,
                previous: $apiException ?? $formException
            );
        }

        $this->authenticated = true;
    }

    /**
     * @return EcarsTradeListingData[]
     */
    public function search(SearchCriteriaData $criteria): array
    {
        $this->authenticate();

        $perPage = max(1, (int) config('ecarstrade.search.per_page', 20));
        $maxPages = max(1, (int) config('ecarstrade.search.max_pages', 3));
        $results = $this->performSearch($criteria, $perPage, $maxPages);
        if ($results !== [] || trim($this->resolveModelSearchLabel($criteria)) === '') {
            return $results;
        }

        $fallbackStrategies = $this->buildBroaderSearchStrategies($criteria, $maxPages);
        foreach ($fallbackStrategies as $strategy) {
            Log::info('eCarsTrade retrying broader search strategy', $this->withRuntimeContext([
                'strategy' => $strategy['name'],
                'query_override' => $strategy['query_override'],
                'omit_query' => $strategy['omit_query'],
                'max_pages' => $strategy['max_pages'],
            ]));

            $fallbackResults = $this->performSearch(
                $criteria,
                $perPage,
                $strategy['max_pages'],
                $strategy['query_override'],
                $strategy['omit_query']
            );

            Log::info('eCarsTrade broader search strategy result', $this->withRuntimeContext([
                'strategy' => $strategy['name'],
                'result_count' => count($fallbackResults),
            ]));

            if ($fallbackResults !== []) {
                return $fallbackResults;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchListingDetails(EcarsTradeListingData $listing): array
    {
        $this->authenticate();

        $url = $this->normalizeUrl($listing->url);
        if ($url === null) {
            return [];
        }

        try {
            $response = $this->requestListingDetailPage($url);
            $this->guardSuccessfulResponse($response, 'page detail annonce eCarsTrade');
        } catch (Throwable $exception) {
            $this->logAuthWarning('eCarsTrade listing detail fetch failed', [
                'listing_url' => $url,
                'source_ref' => $listing->sourceRef,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $html = $response->body();
        $images = $this->extractListingImageUrlsFromHtml($html);
        $documentUrls = $this->extractListingDocumentUrlsFromHtml($html);
        $documents = array_map(function (string $documentUrl): array {
            $path = (string) parse_url($documentUrl, PHP_URL_PATH);
            $filename = $path !== '' ? basename($path) : '';

            return [
                'type' => $this->guessDocumentType($documentUrl),
                'title' => $filename !== '' ? $filename : 'document',
                'url' => $documentUrl,
            ];
        }, $documentUrls);

        return [
            'images' => $images,
            'documents' => $documents,
            'raw' => [
                'detail_url' => $url,
                'effective_url' => (string) Arr::get($response->handlerStats(), 'url', $url),
                'image_count' => count($images),
                'document_count' => count($documents),
            ],
        ];
    }

    private function fetchLoginPage(): Response
    {
        return $this->httpClient()->get($this->resolveUrl((string) config('ecarstrade.login_url')));
    }

    private function fetchSearchPage(): Response
    {
        return $this->httpClient()->get($this->resolveSearchUrl());
    }

    private function requestListingDetailPage(string $url): Response
    {
        return $this->httpClient()
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get($url);
    }

    private function submitLogin(array $payload): Response
    {
        return $this->httpClient()
            ->asForm()
            ->post($this->resolveUrl((string) config('ecarstrade.login_url')), $payload);
    }

    private function submitApiLogin(array $payload, ?string $apiUrl = null): Response
    {
        $resolvedApiUrl = $apiUrl !== null && $apiUrl !== ''
            ? $apiUrl
            : $this->resolveAuthApiUrl();

        return $this->httpClient()
            ->withHeaders([
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => $this->resolveAuthOrigin($resolvedApiUrl),
                'Referer' => $this->resolveUrl((string) config('ecarstrade.login_url')),
            ])
            ->asJson()
            ->post($resolvedApiUrl, $payload);
    }

    private function fetchAuthProbePage(): Response
    {
        $probeUrl = (string) Arr::get(config('ecarstrade.auth', []), 'probe_url', '');
        if ($probeUrl === '') {
            return $this->fetchSearchPage();
        }

        return $this->httpClient()->get($this->resolveUrl($probeUrl));
    }

    private function requestFutureApi(array $payload): Response
    {
        $queryString = $this->buildQueryString($payload);
        $url = $this->resolveFutureApiUrl();

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        return $this->httpClient()
            ->withHeaders([
                'Accept' => '*/*',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get($url);
    }

    private function requestSearchPage(array $payload): Response
    {
        $queryString = $this->buildQueryString($payload);
        $url = $this->resolveSearchUrl();

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        return $this->httpClient()
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get($url);
    }

    private function buildLoginPayload(?string $csrfToken): array
    {
        $authConfig = (array) config('ecarstrade.auth', []);
        $payload = [
            Arr::get($authConfig, 'email_field', 'login') => $this->resolveLoginIdentifier(),
            Arr::get($authConfig, 'password_field', 'pass') => (string) config('ecarstrade.password'),
        ];

        $rememberField = trim((string) Arr::get($authConfig, 'remember_field', ''));
        if ($rememberField !== '') {
            $payload[$rememberField] = Arr::get($authConfig, 'remember_value', '1');
        }

        $csrfField = trim((string) Arr::get($authConfig, 'csrf_field', ''));
        if ($csrfField !== '' && $csrfToken !== null && $csrfToken !== '') {
            $payload[$csrfField] = $csrfToken;
        }

        return $payload;
    }

    private function buildApiLoginPayload(): array
    {
        $authConfig = (array) config('ecarstrade.auth', []);

        return [
            Arr::get($authConfig, 'api_username_field', 'username') => $this->resolveLoginIdentifier(),
            Arr::get($authConfig, 'api_password_field', 'password') => (string) config('ecarstrade.password'),
        ];
    }

    private function attemptApiAuthentication(): ?Throwable
    {
        $payload = $this->buildApiLoginPayload();
        $lastException = null;

        foreach ($this->resolveCandidateAuthApiUrls() as $candidateApiUrl) {
            try {
                $this->logAuthInfo('eCarsTrade auth API attempt', [
                    'api_url' => $candidateApiUrl,
                    'login' => $this->maskEmail($this->resolveLoginIdentifier()),
                    'payload_keys' => array_keys($payload),
                ]);

                $apiResponse = $this->submitApiLogin($payload, $candidateApiUrl);
                $this->guardSuccessfulApiLogin($apiResponse);
                if (!(bool) Arr::get(config('ecarstrade.auth', []), 'skip_probe_after_api_auth', true)) {
                    $this->guardAuthenticatedProbe('api auth', [
                        'candidate_api_url' => $candidateApiUrl,
                    ]);
                } else {
                    $this->logAuthInfo('eCarsTrade auth API accepted without probe', [
                        'candidate_api_url' => $candidateApiUrl,
                        'access_token_present' => $this->accessToken !== null,
                        'refresh_token_present' => $this->refreshToken !== null,
                        'cookies' => $this->exportCookies(),
                    ]);
                }

                return null;
            } catch (Throwable $exception) {
                $lastException = $exception;
                $this->logAuthWarning('eCarsTrade auth API failed', [
                    'api_url' => $candidateApiUrl,
                    'message' => $exception->getMessage(),
                    'cookies' => $this->exportCookies(),
                ]);
            }
        }

        return $lastException;
    }

    private function bootstrapRuntimeAuthConfig(string $html): void
    {
        $runtimeApiUrl = $this->extractJavascriptAssignment($html, 'ect_auth_service');
        if ($runtimeApiUrl !== null && $runtimeApiUrl !== '') {
            $this->runtimeAuthApiUrl = $this->resolveUrl($runtimeApiUrl);
        }

        $runtimeRefreshUrl = $this->extractJavascriptAssignment($html, 'ect_auth_refresh_token_service');
        if ($runtimeRefreshUrl !== null && $runtimeRefreshUrl !== '') {
            $this->runtimeRefreshApiUrl = $this->resolveUrl($runtimeRefreshUrl);
        }

        $runtimeCookieLifetime = $this->extractJavascriptAssignment($html, 'ect_cookie_lifetime');
        if ($runtimeCookieLifetime !== null && is_numeric($runtimeCookieLifetime)) {
            $this->runtimeAuthCookieLifetime = (int) $runtimeCookieLifetime;
        }
    }

    private function extractJavascriptAssignment(string $html, string $variable): ?string
    {
        $pattern = '/\b' . preg_quote($variable, '/') . '\s*=\s*["\']([^"\']+)["\']/i';
        if (preg_match($pattern, $html, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    private function buildFutureApiPayload(
        SearchCriteriaData $criteria,
        int $start,
        int $perPage,
        ?string $queryOverride = null,
        bool $omitQuery = false,
    ): array
    {
        $filters = (array) config('ecarstrade.search.filters', []);
        $defaults = (array) config('ecarstrade.search.defaults', []);

        $payload = [
            'request_type' => $defaults['request_type'] ?? 'cars',
            'auction_type' => $defaults['auction_type'] ?? 'search',
            'start' => $start,
            'perpage' => $perPage,
            'sort' => (string) config('ecarstrade.search.sort', 'time_left.asc'),
        ];

        $makeId = $this->resolveMakeId($criteria->make);
        if ($makeId !== null) {
            $payload[$filters['make'] ?? 'mark[]'] = [$makeId];
        }

        $queryValue = $this->resolveSearchQueryValue($criteria, $makeId === null, $queryOverride, $omitQuery);
        if ($queryValue !== '') {
            $payload[$filters['query'] ?? 'search'] = $queryValue;
        }

        $priceKey = $filters['price_max'] ?? 'price_to';
        $yearKey = $filters['year_min'] ?? 'regist';

        $payload[$priceKey] = (int) round($criteria->budgetMax);
        $payload[$yearKey] = $criteria->yearMin;

        $fuelValue = $this->mapFuelForEcarsTrade($criteria->fuel);
        if ($fuelValue !== null) {
            $payload[$filters['fuel'] ?? 'fuel[]'] = [$fuelValue];
        }

        $gearboxValue = $this->mapGearboxForEcarsTrade($criteria->transmission);
        if ($gearboxValue !== null) {
            $payload[$filters['transmission'] ?? 'gearbox[]'] = [$gearboxValue];
        }

        $colorValue = $this->mapColorForEcarsTrade($criteria->color);
        if ($colorValue !== null) {
            $payload[$filters['color'] ?? 'color[]'] = [$colorValue];
        }

        return $payload;
    }

    private function buildFreeTextSearch(SearchCriteriaData $criteria, bool $forceFallback = false): string
    {
        $mode = (string) config('ecarstrade.search.free_text_mode', 'make_model');

        if ($mode === 'none' && !$forceFallback) {
            return '';
        }

        if ($mode === 'none' && $forceFallback) {
            $mode = 'make_model';
        }

        return match ($mode) {
            'model' => trim($this->resolveModelSearchLabel($criteria)),
            'make' => trim($criteria->make),
            default => trim($criteria->make . ' ' . $this->resolveModelSearchLabel($criteria)),
        };
    }

    private function resolveSearchQueryValue(
        SearchCriteriaData $criteria,
        bool $forceFallback = false,
        ?string $queryOverride = null,
        bool $omitQuery = false,
    ): string {
        if ($omitQuery) {
            return '';
        }

        if ($queryOverride !== null) {
            return trim($queryOverride);
        }

        $queryValue = $this->buildFreeTextSearch($criteria, $forceFallback);
        if ($queryValue !== '') {
            return $queryValue;
        }

        return trim($this->resolveModelSearchLabel($criteria));
    }

    private function extractLoginCsrfToken(string $html): ?string
    {
        $xpath = $this->createXPath($html);
        $selectors = (array) config('ecarstrade.selectors.csrf', []);

        foreach ($selectors as $selector) {
            $value = trim((string) $xpath->evaluate($selector));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return EcarsTradeListingData[]
     */
    private function parseFutureApiResponse(string $body, SearchCriteriaData $criteria): array
    {
        $objects = $this->decodeConcatenatedJsonObjects($body);
        $results = [];

        foreach ($objects as $item) {
            if (!is_array($item) || !isset($item['result'])) {
                continue;
            }

            $listing = $this->extractListingFromFutureApiItem($item, $criteria);
            if ($listing !== null) {
                $results[] = $listing;
            }
        }

        return $results;
    }

    /**
     * @return EcarsTradeListingData[]
     */
    private function parseSearchPageResponse(string $html, SearchCriteriaData $criteria): array
    {
        $xpath = $this->createXPath($html);
        $nodes = $this->querySearchResultNodes($xpath);
        if ($nodes === false || $nodes->length === 0) {
            return [];
        }

        $results = [];
        foreach ($nodes as $node) {
            $listing = $this->extractListingFromSearchCard($xpath, $node, $criteria);
            if ($listing !== null) {
                $results[] = $listing;
            }
        }

        return $results;
    }

    private function looksLikeSearchResultsPage(string $html): bool
    {
        if (
            str_contains($html, 'Resultats de la recherche')
            || str_contains($html, 'Search results')
        ) {
            return true;
        }

        $xpath = $this->createXPath($html);
        $nodes = $this->querySearchResultNodes($xpath);

        return $nodes !== false && $nodes->length > 0;
    }

    private function querySearchResultNodes(DOMXPath $xpath)
    {
        $queries = [
            '//*[contains(@class, "car-item")]',
            '//*[@data-itemid and (.//*[contains(@class, "item-title")] or .//a[contains(@href, "/cars/")])]',
            '//*[contains(@class, "search-results")]//*[@data-itemid]',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes !== false && $nodes->length > 0) {
                return $nodes;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeConcatenatedJsonObjects(string $body): array
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                return array_values(array_filter($decoded, static fn ($item) => is_array($item)));
            }

            if (isset($decoded['result']) && is_string($decoded['result'])) {
                return [$decoded];
            }

            foreach (['results', 'data', 'items'] as $key) {
                $bucket = $decoded[$key] ?? null;
                if (is_array($bucket) && array_is_list($bucket)) {
                    return array_values(array_filter($bucket, static fn ($item) => is_array($item)));
                }
            }
        }

        $objects = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
        $escaped = false;

        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $char = $body[$index];

            if ($depth === 0 && $char !== '{') {
                continue;
            }

            $buffer .= $char;

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    $decoded = json_decode($buffer, true);
                    if (is_array($decoded) && $decoded !== []) {
                        $objects[] = $decoded;
                    }

                    $buffer = '';
                }
            }
        }

        return $objects;
    }

    private function extractListingFromFutureApiItem(array $item, SearchCriteriaData $criteria): ?EcarsTradeListingData
    {
        $cardHtml = base64_decode((string) ($item['result'] ?? ''), true);
        if ($cardHtml === false || trim($cardHtml) === '') {
            return null;
        }

        $xpath = $this->createXPath($cardHtml);
        $root = $this->findCardRoot($xpath);

        $externalId = (string) ($item['car_id'] ?? $this->extractXPathString($xpath, $root, 'string((.//div[contains(@class, "car-item")])[1]/@data-itemid)'));
        $title = $this->extractXPathString($xpath, $root, 'normalize-space((.//*[contains(@class, "item-title")]//span)[1])');
        $href = $this->extractXPathString($xpath, $root, 'string((.//*[contains(@class, "item-title")]//a[@href])[1]/@href)');
        $url = $this->normalizeUrl($href ?: ($externalId !== '' ? '/cars/' . $externalId : null));

        if ($url === null) {
            return null;
        }

        $priceText = $this->extractXPathString($xpath, $root, 'normalize-space((.//*[contains(@class, "car-price")]//*[contains(text(), "EUR")])[1])');
        $price = $this->extractMoney($priceText);

        $apiData = is_array($item['data'] ?? null) ? $item['data'] : [];
        $auctionType = strtolower(trim((string) (
            $this->extractXPathString($xpath, $root, 'string(./@data-auction-type)')
            ?: ($apiData['auction_type'] ?? '')
        )));
        $visibleType = strtolower(trim((string) $this->extractXPathString($xpath, $root, 'string(./@data-visible-type)')));
        $liveAuction = trim((string) $this->extractXPathString($xpath, $root, 'string(./@data-live-auction)'));

        if ($price === null) {
            $price = $this->extractMoney((string) ($apiData['buy_now'] ?? ''))
                ?? $this->extractMoney((string) ($apiData['price'] ?? ''))
                ?? $this->extractMoney((string) ($apiData['max_bid'] ?? ''));
        }

        $yearText = $this->extractFeatureValue($xpath, $root, 'First registration date');
        $mileageText = $this->extractFeatureValue($xpath, $root, 'Mileage');
        $fuelText = $this->extractFeatureValue($xpath, $root, 'Fuel');
        $gearboxText = $this->extractFeatureValue($xpath, $root, 'Gearbox');
        $countryText = $this->extractCountryOfOrigin($xpath, $root);
        $listingType = $this->determineListingType(
            $auctionType,
            $visibleType,
            $liveAuction,
            $apiData,
            $cardHtml,
            $priceText
        );

        $detectedMake = $criteria->make ?: ($this->titleContainsValue($title, $criteria->make) ? $criteria->make : null);
        $detectedModel = $this->detectModelFromTitle($title, $criteria);

        return EcarsTradeListingData::fromArray([
            'source_ref' => $externalId !== '' ? $externalId : md5($url),
            'url' => $url,
            'title' => $title !== '' ? $title : trim($criteria->make . ' ' . $this->resolveModelSearchLabel($criteria)),
            'make' => $detectedMake,
            'model' => $detectedModel,
            'price' => $price,
            'year' => $this->extractYear($yearText),
            'fuel' => $this->canonicalizeFuel($fuelText),
            'gearbox' => $this->canonicalizeGearbox($gearboxText),
            'mileage' => $this->extractInteger($mileageText),
            'color' => null,
            'raw' => [
                'car_id' => $externalId,
                'year_text' => $yearText,
                'mileage_text' => $mileageText,
                'fuel_text' => $fuelText,
                'gearbox_text' => $gearboxText,
                'country_origin' => $countryText,
                'price_text' => $priceText,
                'listing_type' => $listingType,
                'listing_type_label' => $this->listingTypeLabel($listingType),
                'auction_type' => $auctionType !== '' ? $auctionType : null,
                'visible_type' => $visibleType !== '' ? $visibleType : null,
                'live_auction' => $liveAuction !== '' ? $liveAuction : null,
                'api_data' => $apiData,
                'card_html_excerpt' => Str::limit($this->normalizeWhitespace(strip_tags($cardHtml)), 1200),
            ],
        ]);
    }

    private function extractListingFromSearchCard(DOMXPath $xpath, DOMNode $root, SearchCriteriaData $criteria): ?EcarsTradeListingData
    {
        $externalId = $this->extractXPathString($xpath, $root, 'string(./@data-itemid)');
        $title = $this->extractXPathString($xpath, $root, 'normalize-space((.//*[contains(@class, "item-title")]//span)[1])');
        $href = $this->extractXPathString($xpath, $root, 'string((.//*[contains(@class, "item-title")]//a[@href])[1]/@href)');
        $url = $this->normalizeUrl($href ?: ($externalId ? '/cars/' . $externalId : null));

        if ($url === null) {
            return null;
        }

        $priceText = $this->extractXPathString($xpath, $root, 'normalize-space((.//*[contains(@class, "car-price")]//*[contains(text(), "EUR")])[1])');
        $price = $this->extractMoney($priceText);
        $yearText = $this->extractFeatureValue($xpath, $root, 'First registration date');
        $mileageText = $this->extractFeatureValue($xpath, $root, 'Mileage');
        $fuelText = $this->extractFeatureValue($xpath, $root, 'Fuel');
        $gearboxText = $this->extractFeatureValue($xpath, $root, 'Gearbox');
        $cardHtml = $root->ownerDocument?->saveHTML($root) ?: '';
        $listingType = $this->determineListingType(
            strtolower(trim((string) $this->extractXPathString($xpath, $root, 'string(./@data-auction-type)'))),
            strtolower(trim((string) $this->extractXPathString($xpath, $root, 'string(./@data-visible-type)'))),
            trim((string) $this->extractXPathString($xpath, $root, 'string(./@data-live-auction)')),
            [],
            $cardHtml,
            $priceText
        );

        $detectedMake = $criteria->make ?: ($this->titleContainsValue($title, $criteria->make) ? $criteria->make : null);
        $detectedModel = $this->detectModelFromTitle($title, $criteria);

        return EcarsTradeListingData::fromArray([
            'source_ref' => $externalId !== null && $externalId !== '' ? $externalId : md5($url),
            'url' => $url,
            'title' => $title !== '' ? $title : trim($criteria->make . ' ' . $this->resolveModelSearchLabel($criteria)),
            'make' => $detectedMake,
            'model' => $detectedModel,
            'price' => $price,
            'year' => $this->extractYear($yearText),
            'fuel' => $this->canonicalizeFuel($fuelText),
            'gearbox' => $this->canonicalizeGearbox($gearboxText),
            'mileage' => $this->extractInteger($mileageText),
            'color' => null,
            'raw' => [
                'car_id' => $externalId,
                'year_text' => $yearText,
                'mileage_text' => $mileageText,
                'fuel_text' => $fuelText,
                'gearbox_text' => $gearboxText,
                'price_text' => $priceText,
                'listing_type' => $listingType,
                'listing_type_label' => $this->listingTypeLabel($listingType),
                'card_html_excerpt' => Str::limit($this->normalizeWhitespace(strip_tags($cardHtml)), 1200),
            ],
        ]);
    }

    private function findCardRoot(DOMXPath $xpath): ?DOMNode
    {
        $nodes = $this->querySearchResultNodes($xpath);

        return $nodes !== false && $nodes->length > 0 ? $nodes->item(0) : null;
    }

    /**
     * @return EcarsTradeListingData[]
     */
    private function performSearch(
        SearchCriteriaData $criteria,
        int $perPage,
        int $maxPages,
        ?string $queryOverride = null,
        bool $omitQuery = false,
    ): array {
        $seenRefs = [];
        $results = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $start = $page * $perPage;
            $payload = $this->buildFutureApiPayload($criteria, $start, $perPage, $queryOverride, $omitQuery);
            $response = $this->requestFutureApi($payload);
            $this->guardSuccessfulResponse($response, 'future_api.php');
            $this->guardFutureApiAuthenticated($response, $page, $payload);

            $pageListings = $this->parseFutureApiResponse($response->body(), $criteria);
            if ($page === 0 && $pageListings === []) {
                Log::warning('eCarsTrade future_api returned zero parsed listings', $this->withRuntimeContext([
                    'page' => $page,
                    'payload' => $payload,
                    'effective_url' => (string) Arr::get($response->handlerStats(), 'url', ''),
                    'body_sample' => $this->bodySample($response->body()),
                ]));

                $htmlFallbackResponse = $this->requestSearchPage($payload);
                $this->guardSuccessfulResponse($htmlFallbackResponse, 'page de recherche HTML');
                $this->guardFutureApiAuthenticated($htmlFallbackResponse, $page, $payload);

                $pageListings = $this->parseSearchPageResponse($htmlFallbackResponse->body(), $criteria);

                Log::warning('eCarsTrade HTML search fallback result', $this->withRuntimeContext([
                    'page' => $page,
                    'payload' => $payload,
                    'parsed_count' => count($pageListings),
                    'effective_url' => (string) Arr::get($htmlFallbackResponse->handlerStats(), 'url', ''),
                    'body_sample' => $this->bodySample($htmlFallbackResponse->body()),
                ]));
            }
            if ($pageListings === []) {
                break;
            }

            foreach ($pageListings as $listing) {
                $dedupeKey = $listing->sourceRef ?: md5($listing->url);
                if (isset($seenRefs[$dedupeKey])) {
                    continue;
                }

                $seenRefs[$dedupeKey] = true;
                $results[] = $listing;
            }

            if (count($pageListings) < $perPage) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array<int, array{name: string, query_override: ?string, omit_query: bool, max_pages: int}>
     */
    private function buildBroaderSearchStrategies(SearchCriteriaData $criteria, int $maxPages): array
    {
        $modelSearch = trim($this->resolveModelSearchLabel($criteria));
        if ($modelSearch === '') {
            return [];
        }

        $strategies = [];
        $defaultQuery = $this->resolveSearchQueryValue($criteria);
        $makeAndModelQuery = trim($criteria->make . ' ' . $modelSearch);

        if ($makeAndModelQuery !== '' && $makeAndModelQuery !== $defaultQuery) {
            $strategies[] = [
                'name' => 'make_and_model_query',
                'query_override' => $makeAndModelQuery,
                'omit_query' => false,
                'max_pages' => $maxPages,
            ];
        }

        $strategies[] = [
            'name' => 'make_only_filters',
            'query_override' => null,
            'omit_query' => true,
            'max_pages' => max($maxPages, 5),
        ];

        return $strategies;
    }

    private function determineListingType(
        string $auctionType,
        string $visibleType,
        string $liveAuction,
        array $apiData,
        string $cardHtml,
        ?string $priceText,
    ): string {
        $signals = strtolower(trim(implode(' ', array_filter([
            $cardHtml,
            (string) $priceText,
            (string) Arr::get($apiData, 'auction_title', ''),
            (string) Arr::get($apiData, 'label', ''),
            (string) Arr::get($apiData, 'type', ''),
        ]))));

        if (
            $auctionType === 'stock'
            || $visibleType === 'close'
            || str_contains($signals, 'new price')
            || str_contains($signals, 'our stock')
            || str_contains($signals, 'prix fixe')
        ) {
            return 'fixed_price';
        }

        if ($liveAuction === '1' || $auctionType !== '') {
            return 'auction';
        }

        return 'auction';
    }

    private function listingTypeLabel(string $listingType): string
    {
        return $listingType === 'fixed_price' ? 'Prix fixe' : 'Enchere';
    }

    private function extractFeatureValue(DOMXPath $xpath, ?DOMNode $contextNode, string $tooltipTitle): ?string
    {
        $literal = $this->xpathLiteral($tooltipTitle);

        $queries = [
            sprintf('normalize-space((.//*[@data-original-title=%s]//*[contains(@class, "feature-value")])[1])', $literal),
            sprintf('normalize-space((.//*[@data-original-title=%s]//*[contains(@class, "feature-value")]//span)[1])', $literal),
        ];

        foreach ($queries as $query) {
            $value = $this->extractXPathString($xpath, $contextNode, $query);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        $fallbackIndex = match ($tooltipTitle) {
            'First registration date' => 1,
            'Gearbox' => 2,
            'Mileage' => 3,
            'Fuel' => 4,
            default => null,
        };

        return $fallbackIndex !== null
            ? $this->extractFeatureValueByIndex($xpath, $contextNode, $fallbackIndex)
            : null;
    }

    private function extractFeatureValueByIndex(DOMXPath $xpath, ?DOMNode $contextNode, int $index): ?string
    {
        return $this->extractXPathString(
            $xpath,
            $contextNode,
            sprintf(
                'normalize-space((.//*[contains(@class, "item-feature")]//*[contains(@class, "feature-value")])[%d])',
                $index
            )
        );
    }

    private function extractCountryOfOrigin(DOMXPath $xpath, ?DOMNode $contextNode): ?string
    {
        $value = $this->extractXPathString(
            $xpath,
            $contextNode,
            'string((.//*[contains(@class, "icon-country-origin")])[1]/@data-original-title)'
        );

        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/(?:Country of origin|Pays d\'origine)\s*:\s*(.+)$/iu', $value, $matches) === 1) {
            return trim($matches[1]);
        }

        return $value;
    }

    private function extractXPathString(DOMXPath $xpath, ?DOMNode $contextNode, string $expression): ?string
    {
        $value = trim((string) $xpath->evaluate($expression, $contextNode));

        return $value !== '' ? $this->normalizeWhitespace($value) : null;
    }

    private function resolveMakeId(string $make): ?string
    {
        $normalizedTarget = $this->normalizeLookupValue($make);
        if ($normalizedTarget === '') {
            return null;
        }

        foreach ($this->getMakeMap() as $label => $id) {
            if ($label === $normalizedTarget) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function getMakeMap(): array
    {
        if ($this->makeMap !== null) {
            return $this->makeMap;
        }

        $response = $this->fetchSearchPage();
        $this->guardSuccessfulResponse($response, 'page de recherche');

        $xpath = $this->createXPath($response->body());
        $options = $xpath->query('//select[@name="mark[]"]//option[@value and @value != "0"]');

        $map = [];
        $scores = [];

        if ($options !== false) {
            foreach ($options as $option) {
                $label = trim((string) $xpath->evaluate('string(@name)', $option));
                if ($label === '') {
                    $label = trim(preg_replace('/\s*\(\d+\)\s*$/', '', $option->textContent ?? '') ?? '');
                }

                $value = trim((string) $xpath->evaluate('string(@value)', $option));
                if ($label === '' || $value === '') {
                    continue;
                }

                $normalizedLabel = $this->normalizeLookupValue($label);
                if ($normalizedLabel === '') {
                    continue;
                }

                $count = 0;
                if (preg_match('/\((\d+)\)\s*$/', trim($option->textContent ?? ''), $matches) === 1) {
                    $count = (int) $matches[1];
                }

                if (!isset($scores[$normalizedLabel]) || $count > $scores[$normalizedLabel]) {
                    $scores[$normalizedLabel] = $count;
                    $map[$normalizedLabel] = $value;
                }
            }
        }

        $this->makeMap = $map;

        return $this->makeMap;
    }

    private function mapFuelForEcarsTrade(?string $fuel): ?string
    {
        $normalized = $this->normalizeLookupValue((string) $fuel);

        return match ($normalized) {
            '', 'tous', 'all' => null,
            'diesel' => 'Diesel',
            'essence', 'petrol' => 'Petrol',
            'electric', 'electrique' => 'Electric',
            'lpg', 'gpl' => 'LPG',
            'naturalgas', 'gaznaturel', 'gaz' => 'Natural Gas',
            'hybride', 'hybrid', 'hybridpetrolelectric' => 'Hybrid (petrol/electric)',
            'hybriddieselelectric' => 'Hybrid (diesel/electric)',
            default => $fuel,
        };
    }

    private function mapGearboxForEcarsTrade(?string $gearbox): ?string
    {
        $normalized = $this->normalizeLookupValue((string) $gearbox);

        return match ($normalized) {
            '', 'toutes', 'all' => null,
            'automatic', 'automatique', 'auto' => 'Automatic',
            'manual', 'manuelle' => 'Manual',
            'semiautomatic', 'semiautomatique' => 'Semi-automatic',
            default => $gearbox,
        };
    }

    private function mapColorForEcarsTrade(?string $color): ?string
    {
        $normalized = $this->normalizeLookupValue((string) $color);

        return match ($normalized) {
            '', 'indifferent', 'all' => null,
            'white', 'blanc' => 'White',
            'black', 'noir' => 'Black',
            'gray', 'grey', 'gris' => 'Grey',
            'silver', 'argent' => 'Silver',
            'blue', 'bleu' => 'Blue',
            'red', 'rouge' => 'Red',
            'green', 'vert' => 'Green',
            'beige' => 'Beige',
            'brown', 'marron' => 'Brown',
            'yellow', 'jaune' => 'Yellow',
            'orange' => 'Orange',
            default => $color,
        };
    }

    private function canonicalizeFuel(?string $fuel): ?string
    {
        $baseFuel = trim((string) preg_split('/\s*,\s*/u', (string) $fuel, 2)[0]);
        $normalized = $this->normalizeLookupValue($baseFuel);

        return match ($normalized) {
            '' => null,
            'diesel' => 'diesel',
            'petrol', 'essence' => 'essence',
            'electric', 'electrique' => 'electrique',
            'hybridpetrolelectric', 'hybriddieselelectric', 'hybride', 'hybrid' => 'hybride',
            'lpg', 'gpl' => 'gpl',
            'naturalgas', 'gaznaturel', 'gaz' => 'gaz',
            default => Str::lower($baseFuel),
        };
    }

    private function canonicalizeGearbox(?string $gearbox): ?string
    {
        $normalized = $this->normalizeLookupValue((string) $gearbox);

        return match ($normalized) {
            '' => null,
            'automatic', 'automatique', 'auto' => 'automatic',
            'manual', 'manuelle' => 'manual',
            'semiautomatic', 'semiautomatique' => 'semi-automatic',
            default => Str::lower(trim((string) $gearbox)),
        };
    }

    private function titleContainsValue(?string $title, ?string $expected): bool
    {
        $normalizedTitle = $this->normalizeLookupValue((string) $title);
        $normalizedExpected = $this->normalizeLookupValue((string) $expected);

        if ($normalizedTitle === '' || $normalizedExpected === '') {
            return false;
        }

        return str_contains($normalizedTitle, $normalizedExpected);
    }

    private function resolveModelSearchLabel(SearchCriteriaData $criteria): string
    {
        return trim((string) ($criteria->model ?? ''));
    }

    private function detectModelFromTitle(?string $title, SearchCriteriaData $criteria): ?string
    {
        $expectedModels = VehicleCatalog::modelsForSelection($criteria->make, $criteria->model);

        if ($expectedModels !== []) {
            usort($expectedModels, static fn (string $left, string $right) => strlen($right) <=> strlen($left));

            foreach ($expectedModels as $expectedModel) {
                if ($this->titleContainsValue($title, $expectedModel)) {
                    return $expectedModel;
                }
            }
        }

        return $this->titleContainsValue($title, $criteria->model)
            ? $criteria->model
            : null;
    }

    private function normalizeLookupValue(string $value): string
    {
        $normalized = Str::lower($value);
        $normalized = str_replace(
            [' ', '-', '/', '\\', '.', ',', '(', ')', '\''],
            '',
            $normalized
        );

        return trim($normalized);
    }

    private function buildQueryString(array $payload): string
    {
        $pairs = [];

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }

                    $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $item);
                }

                continue;
            }

            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $pairs);
    }

    private function createXPath(string $html): DOMXPath
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        return new DOMXPath($dom);
    }

    private function normalizeUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return rtrim((string) config('ecarstrade.base_url'), '/') . '/' . ltrim($url, '/');
    }

    /**
     * @return array<int, string>
     */
    private function extractListingImageUrlsFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $urls = [];
        $xpath = $this->createXPath($html);
        $nodes = $xpath->query('//img[@src or @data-src or @data-original or @data-lazy or @srcset] | //a[@data-src or @href]');

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                foreach (['src', 'data-src', 'data-original', 'data-lazy', 'href'] as $attribute) {
                    $candidate = trim((string) $xpath->evaluate("string(@{$attribute})", $node));
                    $normalized = $this->normalizeExtractedUrl($candidate);

                    if ($normalized !== null && $this->isLikelyVehicleImageUrl($normalized)) {
                        $urls[] = $normalized;
                    }
                }

                $srcset = trim((string) $xpath->evaluate('string(@srcset)', $node));
                if ($srcset !== '') {
                    foreach (explode(',', $srcset) as $entry) {
                        $parts = preg_split('/\s+/', trim($entry));
                        $candidate = $parts[0] ?? null;
                        $normalized = $this->normalizeExtractedUrl(is_string($candidate) ? $candidate : null);
                        if ($normalized !== null && $this->isLikelyVehicleImageUrl($normalized)) {
                            $urls[] = $normalized;
                        }
                    }
                }
            }
        }

        preg_match_all('/https?:\\\\?\/\\\\?\/[^"\'\s<>]+/i', $html, $matches);
        foreach ($matches[0] ?? [] as $rawMatch) {
            $normalized = $this->normalizeExtractedUrl((string) $rawMatch);
            if ($normalized !== null && $this->isLikelyVehicleImageUrl($normalized)) {
                $urls[] = $normalized;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return array<int, string>
     */
    private function extractListingDocumentUrlsFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $urls = [];
        $xpath = $this->createXPath($html);
        $nodes = $xpath->query('//a[@href]');

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $candidate = trim((string) $xpath->evaluate('string(@href)', $node));
                $normalized = $this->normalizeExtractedUrl($candidate);
                if ($normalized !== null && $this->isLikelyDocumentUrl($normalized)) {
                    $urls[] = $normalized;
                }
            }
        }

        preg_match_all('/https?:\\\\?\/\\\\?\/[^"\'\s<>]+/i', $html, $matches);
        foreach ($matches[0] ?? [] as $rawMatch) {
            $normalized = $this->normalizeExtractedUrl((string) $rawMatch);
            if ($normalized !== null && $this->isLikelyDocumentUrl($normalized)) {
                $urls[] = $normalized;
            }
        }

        return array_values(array_unique($urls));
    }

    private function normalizeExtractedUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $candidate = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        if ($candidate === '' || Str::startsWith($candidate, ['#', 'javascript:', 'mailto:', 'tel:'])) {
            return null;
        }

        $candidate = str_replace(['\\/', '\\u002F'], '/', $candidate);
        $candidate = preg_replace('/\\\\u([0-9a-fA-F]{4})/', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B\"'");

        return $this->normalizeUrl($candidate);
    }

    private function isLikelyVehicleImageUrl(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));
        if ($path === '') {
            return false;
        }

        if (!preg_match('/\.(jpg|jpeg|png|webp|gif|avif)$/i', $path)) {
            return false;
        }

        foreach (['icon', 'logo', 'sprite', 'avatar', 'flag', 'placeholder'] as $blocked) {
            if (str_contains($path, $blocked)) {
                return false;
            }
        }

        return true;
    }

    private function isLikelyDocumentUrl(string $url): bool
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));
        if ($path === '') {
            return false;
        }

        if (preg_match('/\.(pdf|doc|docx|xls|xlsx|xml|csv|zip)$/i', $path)) {
            return true;
        }

        foreach (['/document', '/download', '/files/'] as $hint) {
            if (str_contains($path, $hint)) {
                return true;
            }
        }

        return false;
    }

    private function guessDocumentType(string $url): string
    {
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            str_ends_with($path, '.pdf') => 'pdf',
            str_ends_with($path, '.xml') => 'xml',
            str_ends_with($path, '.doc'), str_ends_with($path, '.docx') => 'doc',
            str_ends_with($path, '.xls'), str_ends_with($path, '.xlsx'), str_ends_with($path, '.csv') => 'sheet',
            default => 'other',
        };
    }

    private function extractMoney(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $cleaned = preg_replace('/[^\d,\.]/', '', $value);
        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $normalized = str_replace('.', '', $cleaned);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($cleaned, ',')) {
            $normalized = preg_match('/,\d{1,2}$/', $cleaned) === 1
                ? str_replace(',', '.', $cleaned)
                : str_replace(',', '', $cleaned);
        } else {
            $normalized = $cleaned;
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function extractYear(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/\b(19|20)\d{2}\b/', $value, $matches) === 1) {
            return (int) $matches[0];
        }

        if (preg_match('/\b(0?[1-9]|1[0-2])\/((19|20)\d{2})\b/', $value, $matches) === 1) {
            return (int) $matches[2];
        }

        return null;
    }

    private function extractInteger(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== null && $digits !== '' ? (int) $digits : null;
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function guardSuccessfulResponse(Response $response, string $step): void
    {
        if ($response->failed()) {
            Log::warning('eCarsTrade HTTP step failed', $this->withRuntimeContext([
                'step' => $step,
                'status' => $response->status(),
                'effective_url' => (string) Arr::get($response->handlerStats(), 'url', ''),
                'cookies' => $this->exportCookies(),
                'body_sample' => $this->bodySample($response->body()),
            ]));

            throw new RuntimeException("Echec HTTP pendant {$step} (status {$response->status()}).");
        }
    }

    private function guardSuccessfulLogin(Response $response): void
    {
        $this->guardSuccessfulResponse($response, 'la connexion eCarsTrade');

        $effectiveUrl = (string) Arr::get($response->handlerStats(), 'url', '');
        $loginSignals = $this->collectAuthSignals($response->body(), $effectiveUrl);
        $this->logAuthInfo('eCarsTrade login response', [
            'status' => $response->status(),
            'effective_url' => $effectiveUrl !== '' ? $effectiveUrl : null,
            'cookies' => $this->exportCookies(),
            'signals' => $loginSignals,
            'body_sample' => $this->bodySample($response->body()),
        ]);

        if ($loginSignals['authenticated']) {
            return;
        }

        $this->guardAuthenticatedProbe('html login', [
            'login_signals' => $loginSignals,
            'login_body_sample' => $this->bodySample($response->body()),
        ]);
    }

    private function guardSuccessfulApiLogin(Response $response): void
    {
        if ($response->failed()) {
            $detail = $this->extractApiErrorDetail($response) ?? 'reponse API non valide';
            $this->logAuthInfo('eCarsTrade auth API response', [
                'status' => $response->status(),
                'cookies' => $this->exportCookies(),
                'body_sample' => $this->bodySample($response->body()),
            ]);

            throw new RuntimeException("API auth eCarsTrade refusee (status {$response->status()}): {$detail}");
        }

        $tokens = $this->extractApiTokens($response);
        if ($tokens === null) {
            $this->logAuthInfo('eCarsTrade auth API response', [
                'status' => $response->status(),
                'cookies' => $this->exportCookies(),
                'body_sample' => $this->bodySample($response->body()),
            ]);

            throw new RuntimeException('API auth eCarsTrade reussie mais aucun token exploitable n\'a ete retourne.');
        }

        $this->persistApiAuthCookies($tokens);

        $this->logAuthInfo('eCarsTrade auth API response', [
            'status' => $response->status(),
            'access_token_present' => $this->accessToken !== null,
            'refresh_token_present' => $this->refreshToken !== null,
            'cookies' => $this->exportCookies(),
            'body_sample' => $this->bodySample($response->body()),
        ]);
    }

    private function guardAuthenticatedProbe(string $context, array $extraContext = []): void
    {
        $probeResponse = $this->fetchAuthProbePage();
        $this->guardSuccessfulResponse($probeResponse, 'la verification de session eCarsTrade');

        $probeEffectiveUrl = (string) Arr::get($probeResponse->handlerStats(), 'url', '');
        $probeSignals = $this->collectAuthSignals($probeResponse->body(), $probeEffectiveUrl);
        $this->logAuthInfo('eCarsTrade auth probe response', [
            'context' => $context,
            'status' => $probeResponse->status(),
            'effective_url' => $probeEffectiveUrl !== '' ? $probeEffectiveUrl : null,
            'cookies' => $this->exportCookies(),
            'signals' => $probeSignals,
            'body_sample' => $this->bodySample($probeResponse->body()),
        ]);

        if ($probeSignals['authenticated']) {
            return;
        }

        $reasons = [];
        if ($this->cookies->count() === 0) {
            $reasons[] = 'aucun cookie de session recu apres le login';
        }
        if ($context === 'api auth' && $this->hasPersistedAuthTokens()) {
            $reasons[] = 'des tokens API ont ete recus mais la session reste consideree comme visiteur';
        }
        if (($extraContext['login_signals']['guest'] ?? false) === true) {
            $reasons[] = 'la reponse du login ressemble encore a une page visiteur';
        }
        if ($probeSignals['guest']) {
            $reasons[] = 'la page probe est encore en mode visiteur';
        }
        if (($extraContext['login_signals']['authenticated_markers'] ?? []) === [] && $probeSignals['authenticated_markers'] === []) {
            $reasons[] = 'aucun marqueur authentifie detecte';
        }
        if (($extraContext['login_signals']['effective_url_match'] ?? false) === false && $probeSignals['effective_url_match'] === false) {
            $reasons[] = 'aucune redirection vers une page attendue apres login';
        }

        $message = 'Connexion eCarsTrade non confirmee. '
            . implode(' | ', $reasons !== [] ? $reasons : ['verifie les champs de login et les marqueurs de session']);

        $this->logAuthWarning('eCarsTrade login not confirmed', array_merge($extraContext, [
            'context' => $context,
            'message' => $message,
            'cookies' => $this->exportCookies(),
            'probe_signals' => $probeSignals,
            'probe_body_sample' => $this->bodySample($probeResponse->body()),
        ]));

        throw new RuntimeException($message);
    }

    /**
     * @return array{access_token: string, refresh_token: string|null}|null
     */
    private function extractApiTokens(Response $response): ?array
    {
        $json = $response->json();
        if (!is_array($json)) {
            return null;
        }

        $payload = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $accessToken = trim((string) ($payload['accessToken'] ?? $payload['access_token'] ?? ''));
        if ($accessToken === '') {
            return null;
        }

        $refreshToken = trim((string) ($payload['refreshToken'] ?? $payload['refresh_token'] ?? ''));

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken !== '' ? $refreshToken : null,
        ];
    }

    /**
     * @param array{access_token: string, refresh_token: string|null} $tokens
     */
    private function persistApiAuthCookies(array $tokens): void
    {
        $this->accessToken = $tokens['access_token'];
        $this->refreshToken = $tokens['refresh_token'];

        $domain = $this->resolveAuthCookieDomain();
        $expires = time() + $this->resolveAuthCookieLifetime();

        $this->storeSyntheticCookie(
            (string) Arr::get(config('ecarstrade.auth', []), 'access_cookie_name', 'eCT/user'),
            $this->accessToken,
            $domain,
            $expires
        );

        if ($this->refreshToken !== null) {
            $this->storeSyntheticCookie(
                (string) Arr::get(config('ecarstrade.auth', []), 'refresh_cookie_name', 'eCT/refresh-token'),
                $this->refreshToken,
                $domain,
                $expires
            );
        }
    }

    private function storeSyntheticCookie(string $name, string $value, string $domain, int $expires): void
    {
        $encodedName = $this->shouldEncodeAuthCookieNames()
            ? rawurlencode($name)
            : $name;

        $this->cookies->setCookie(new SetCookie([
            'Name' => $encodedName,
            'Value' => rawurlencode($value),
            'Domain' => $domain,
            'Path' => '/',
            'Expires' => $expires,
            'Secure' => Str::startsWith($domain, '.') || Str::startsWith((string) config('ecarstrade.base_url'), 'https://'),
            'HttpOnly' => false,
        ]));
    }

    private function resolveSearchUrl(): string
    {
        $searchUrl = (string) config('ecarstrade.search_url', '');
        if ($searchUrl !== '') {
            return $this->resolveUrl($searchUrl);
        }

        return $this->resolveUrl((string) config('ecarstrade.search.path', '/search'));
    }

    private function resolveFutureApiUrl(): string
    {
        $futureApiUrl = (string) config('ecarstrade.future_api_url', '');
        if ($futureApiUrl !== '') {
            return $this->resolveUrl($futureApiUrl);
        }

        return $this->resolveUrl((string) config('ecarstrade.search.future_api_path', '/future_api.php'));
    }

    private function resolveUrl(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return rtrim((string) config('ecarstrade.base_url'), '/') . '/' . ltrim($url, '/');
    }

    private function resolveAuthApiUrl(): string
    {
        if ($this->runtimeAuthApiUrl !== null && $this->runtimeAuthApiUrl !== '') {
            return $this->runtimeAuthApiUrl;
        }

        return $this->resolveUrl((string) Arr::get(config('ecarstrade.auth', []), 'api_url', ''));
    }

    /**
     * @return array<int, string>
     */
    private function resolveCandidateAuthApiUrls(): array
    {
        $primary = $this->resolveAuthApiUrl();
        $candidates = [$primary];

        $frenchCandidate = $this->withPreferredFrenchHost($primary);
        if ($frenchCandidate !== null) {
            $candidates[] = $frenchCandidate;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function withPreferredFrenchHost(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || !is_string($parts['host'])) {
            return null;
        }

        $host = $parts['host'];
        if (Str::startsWith($host, 'fr.')) {
            return null;
        }

        if ($host === 'ecarstrade.com') {
            $parts['host'] = 'fr.ecarstrade.com';
        } elseif (Str::endsWith($host, '.ecarstrade.com')) {
            $parts['host'] = 'fr.ecarstrade.com';
        } else {
            return null;
        }

        return $this->buildUrlFromParts($parts);
    }

    private function resolveAuthOrigin(?string $apiUrl = null): string
    {
        $parts = parse_url($apiUrl !== null && $apiUrl !== '' ? $apiUrl : $this->resolveAuthApiUrl());
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim((string) config('ecarstrade.base_url'), '/');
        }

        return sprintf(
            '%s://%s%s',
            $parts['scheme'],
            $parts['host'],
            isset($parts['port']) ? ':' . $parts['port'] : ''
        );
    }

    private function resolveAuthCookieDomain(): string
    {
        $configured = trim((string) Arr::get(config('ecarstrade.auth', []), 'cookie_domain', ''));
        if ($configured !== '') {
            return $configured;
        }

        $host = parse_url($this->resolveAuthOrigin(), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '.ecarstrade.com';
        }

        $segments = array_values(array_filter(explode('.', $host)));
        if (count($segments) >= 2) {
            return '.' . implode('.', array_slice($segments, -2));
        }

        return $host;
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildUrlFromParts(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : 'https://';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    private function resolveAuthCookieLifetime(): int
    {
        if ($this->runtimeAuthCookieLifetime !== null && $this->runtimeAuthCookieLifetime > 0) {
            return $this->runtimeAuthCookieLifetime;
        }

        return max(60, (int) Arr::get(config('ecarstrade.auth', []), 'cookie_lifetime', 1209600));
    }

    private function shouldEncodeAuthCookieNames(): bool
    {
        return (bool) Arr::get(config('ecarstrade.auth', []), 'encode_cookie_names', true);
    }

    private function requiresAuthentication(): bool
    {
        return (bool) config('ecarstrade.auth.required', false);
    }

    private function prefersApiAuthentication(): bool
    {
        return (bool) Arr::get(config('ecarstrade.auth', []), 'prefer_api', true);
    }

    private function hasCredentialsConfigured(): bool
    {
        return $this->resolveLoginIdentifier() !== ''
            && (string) config('ecarstrade.password') !== '';
    }

    private function resolveLoginIdentifier(): string
    {
        return trim((string) config('ecarstrade.username', config('ecarstrade.email')));
    }

    private function hasPersistedAuthTokens(): bool
    {
        return $this->accessToken !== null;
    }

    /**
     * @return array{
     *   authenticated: bool,
     *   guest: bool,
     *   authenticated_markers: array<int, string>,
     *   guest_markers: array<int, string>,
     *   effective_url_match: bool
     * }
     */
    private function collectAuthSignals(string $html, string $effectiveUrl = ''): array
    {
        $xpath = $this->createXPath($html);
        $authenticatedMarkers = [];
        $guestMarkers = [];

        foreach ((array) config('ecarstrade.selectors.authenticated_markers', []) as $marker) {
            $nodes = $xpath->query($marker);
            if ($nodes !== false && $nodes->length > 0) {
                $authenticatedMarkers[] = $marker;
            }
        }

        if (preg_match('/\b(?:ws_)?user_id\s*=\s*"(?!0\b)(\d+)"/', $html) === 1) {
            $authenticatedMarkers[] = 'user_id_non_zero';
        }

        if (str_contains($html, 'id="authform-popup"')) {
            $guestMarkers[] = 'authform-popup';
        }
        if (str_contains($html, 'show_login_form')) {
            $guestMarkers[] = 'show_login_form';
        }
        if (preg_match('/\b(?:ws_)?user_id\s*=\s*"0"/', $html) === 1) {
            $guestMarkers[] = 'user_id_zero';
        }

        $successPath = (string) config('ecarstrade.auth.success_path_contains', '/search');
        $effectiveUrlMatch = $effectiveUrl !== '' && Str::contains($effectiveUrl, $successPath);

        $authenticated = $authenticatedMarkers !== [];
        $guest = $guestMarkers !== [];

        if (!$authenticated && $effectiveUrlMatch && !$guest && $this->cookies->count() > 0) {
            $authenticated = true;
        }

        return [
            'authenticated' => $authenticated,
            'guest' => $guest,
            'authenticated_markers' => array_values(array_unique($authenticatedMarkers)),
            'guest_markers' => array_values(array_unique($guestMarkers)),
            'effective_url_match' => $effectiveUrlMatch,
        ];
    }

    private function guardFutureApiAuthenticated(Response $response, int $page, array $payload): void
    {
        $body = $response->body();
        $effectiveUrl = (string) Arr::get($response->handlerStats(), 'url', '');
        $signals = $this->collectAuthSignals(
            $body,
            $effectiveUrl
        );

        if ($signals['guest'] && !$signals['authenticated']) {
            if ($signals['effective_url_match'] && $this->looksLikeSearchResultsPage($body)) {
                Log::info('eCarsTrade search results page accepted despite guest markers', $this->withRuntimeContext([
                    'page' => $page,
                    'payload' => $payload,
                    'effective_url' => $effectiveUrl,
                    'signals' => $signals,
                ]));

                return;
            }

            Log::warning('eCarsTrade future_api looks unauthenticated', $this->withRuntimeContext([
                'page' => $page,
                'payload' => $payload,
                'effective_url' => $effectiveUrl,
                'signals' => $signals,
                'cookies' => $this->exportCookies(),
                'body_sample' => $this->bodySample($body),
            ]));

            throw new RuntimeException(
                'La session eCarsTrade semble invitée pendant future_api.php. La connexion n est pas réellement établie.'
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportCookies(): array
    {
        return array_map(static function (array $cookie): array {
            return [
                'name' => $cookie['Name'] ?? null,
                'domain' => $cookie['Domain'] ?? null,
                'path' => $cookie['Path'] ?? null,
                'expires' => $cookie['Expires'] ?? null,
                'secure' => $cookie['Secure'] ?? null,
                'http_only' => $cookie['HttpOnly'] ?? null,
            ];
        }, $this->cookies->toArray());
    }

    private function bodySample(string $html): string
    {
        return Str::limit($this->normalizeWhitespace(strip_tags($html)), 1000);
    }

    private function extractApiErrorDetail(Response $response): ?string
    {
        $json = $response->json();
        if (!is_array($json)) {
            return null;
        }

        $errors = $json['errors'] ?? null;
        if (!is_array($errors) || $errors === []) {
            return null;
        }

        $first = $errors[0] ?? null;
        if (!is_array($first)) {
            return null;
        }

        return $first['detail'] ?? $first['title'] ?? null;
    }

    private function maskEmail(string $email): string
    {
        if ($email === '' || !str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $prefix = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $prefix . '***@' . $domain;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function withRuntimeContext(array $context = []): array
    {
        $runtimeContext = config('ecarstrade.runtime_context', []);

        return array_merge(
            is_array($runtimeContext) ? Arr::where($runtimeContext, static fn ($value) => $value !== null && $value !== '') : [],
            $context
        );
    }

    private function logAuthInfo(string $message, array $context = []): void
    {
        if ((bool) config('ecarstrade.debug', false)) {
            Log::info($message, $this->withRuntimeContext($context));
        }
    }

    private function logAuthWarning(string $message, array $context = []): void
    {
        Log::warning($message, $this->withRuntimeContext($context));
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }

        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        $parts = explode("'", $value);
        $escaped = array_map(
            static fn (string $part): string => "'" . $part . "'",
            $parts
        );

        return 'concat(' . implode(', "\'", ', $escaped) . ')';
    }

    private function httpClient(): PendingRequest
    {
        $client = Http::withOptions([
            'cookies' => $this->cookies,
            'allow_redirects' => true,
            'http_errors' => false,
            'timeout' => (int) config('ecarstrade.timeout', 30),
            'verify' => (bool) config('ecarstrade.ssl_verify', true),
        ])->withHeaders([
            'User-Agent' => (string) config('ecarstrade.user_agent'),
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            'Referer' => $this->resolveUrl((string) config('ecarstrade.base_url')),
        ]);

        if ($this->accessToken !== null) {
            $client = $client->withToken($this->accessToken);
        }

        return $client;
    }
}
