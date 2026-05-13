<?php

return [
    'base_url' => env('ECARSTRADE_BASE_URL', 'https://ecarstrade.com'),
    'login_url' => env('ECARSTRADE_LOGIN_URL', 'https://ecarstrade.com/login'),
    'search_url' => env('ECARSTRADE_SEARCH_URL', 'https://ecarstrade.com/search'),
    'future_api_url' => env('ECARSTRADE_FUTURE_API_URL', 'https://ecarstrade.com/future_api.php'),
    'email' => env('ECARSTRADE_EMAIL'),
    'username' => env('ECARSTRADE_USERNAME', env('ECARSTRADE_EMAIL')),
    'password' => env('ECARSTRADE_PASSWORD'),
    'debug' => filter_var(env('ECARSTRADE_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'connector' => env(
        'ECARSTRADE_CONNECTOR',
        env('ECARSTRADE_MODE', 'fake') === 'live' ? 'http' : 'fake'
    ),
    'timeout' => (int) env('ECARSTRADE_TIMEOUT', 30),
    'ssl_verify' => filter_var(env('ECARSTRADE_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    'user_agent' => env(
        'ECARSTRADE_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0 Safari/537.36'
    ),
    'search_zone' => env('ECARSTRADE_SEARCH_ZONE', 'all_cars'),
    'mode' => env(
        'ECARSTRADE_MODE',
        env('ECARSTRADE_CONNECTOR', 'fake') === 'http' ? 'live' : 'fake'
    ),
    'auth' => [
        'required' => filter_var(
            env(
                'ECARSTRADE_REQUIRE_AUTH',
                env('ECARSTRADE_MODE', 'fake') === 'live' || env('ECARSTRADE_CONNECTOR', 'fake') === 'http'
            ),
            FILTER_VALIDATE_BOOLEAN
        ),
        'prefer_api' => filter_var(env('ECARSTRADE_AUTH_PREFER_API', false), FILTER_VALIDATE_BOOLEAN),
        'email_field' => env('ECARSTRADE_LOGIN_EMAIL_FIELD', 'login'),
        'password_field' => env('ECARSTRADE_LOGIN_PASSWORD_FIELD', 'pass'),
        'csrf_field' => env('ECARSTRADE_LOGIN_CSRF_FIELD', ''),
        'remember_field' => env('ECARSTRADE_LOGIN_REMEMBER_FIELD', 'remember'),
        'remember_value' => env('ECARSTRADE_LOGIN_REMEMBER_VALUE', '1'),
        'success_path_contains' => env('ECARSTRADE_LOGIN_SUCCESS_PATH', '/search'),
        'probe_url' => env('ECARSTRADE_AUTH_PROBE_URL', env('ECARSTRADE_SEARCH_URL', 'https://ecarstrade.com/search')),
        'api_url' => env('ECARSTRADE_AUTH_API_URL', 'https://ecarstrade.com/api/v1/auth/login'),
        'refresh_api_url' => env('ECARSTRADE_AUTH_REFRESH_API_URL', 'https://ecarstrade.com/api/v1/auth/refreshToken'),
        'api_username_field' => env('ECARSTRADE_AUTH_API_USERNAME_FIELD', 'username'),
        'api_password_field' => env('ECARSTRADE_AUTH_API_PASSWORD_FIELD', 'password'),
        'access_cookie_name' => env('ECARSTRADE_AUTH_ACCESS_COOKIE_NAME', 'eCT/user'),
        'refresh_cookie_name' => env('ECARSTRADE_AUTH_REFRESH_COOKIE_NAME', 'eCT/refresh-token'),
        'encode_cookie_names' => filter_var(env('ECARSTRADE_AUTH_ENCODE_COOKIE_NAMES', true), FILTER_VALIDATE_BOOLEAN),
        'skip_probe_after_api_auth' => filter_var(env('ECARSTRADE_SKIP_PROBE_AFTER_API_AUTH', true), FILTER_VALIDATE_BOOLEAN),
        'cookie_domain' => env('ECARSTRADE_AUTH_COOKIE_DOMAIN', ''),
        'cookie_lifetime' => (int) env('ECARSTRADE_AUTH_COOKIE_LIFETIME', 1209600),
    ],
    'search' => [
        'method' => env('ECARSTRADE_SEARCH_METHOD', 'GET'),
        'path' => env('ECARSTRADE_SEARCH_PATH', '/search'),
        'future_api_path' => env('ECARSTRADE_FUTURE_API_PATH', '/future_api.php'),
        'per_page' => (int) env('ECARSTRADE_SEARCH_PER_PAGE', 20),
        'max_pages' => (int) env('ECARSTRADE_SEARCH_MAX_PAGES', 3),
        'sort' => env('ECARSTRADE_SEARCH_SORT', 'time_left.asc'),
        'free_text_mode' => env('ECARSTRADE_SEARCH_FREE_TEXT_MODE', 'none'),
        'filters' => [
            'make' => env('ECARSTRADE_SEARCH_FILTER_MAKE', 'mark[]'),
            'model' => env('ECARSTRADE_SEARCH_FILTER_MODEL', 'model[]'),
            'query' => env('ECARSTRADE_SEARCH_FILTER_QUERY', 'search'),
            'price_max' => env('ECARSTRADE_SEARCH_FILTER_PRICE_MAX', 'price_to'),
            'year_min' => env('ECARSTRADE_SEARCH_FILTER_YEAR_MIN', 'regist'),
            'fuel' => env('ECARSTRADE_SEARCH_FILTER_FUEL', 'fuel[]'),
            'transmission' => env('ECARSTRADE_SEARCH_FILTER_TRANSMISSION', 'gearbox[]'),
            'color' => env('ECARSTRADE_SEARCH_FILTER_COLOR', 'color[]'),
        ],
        'defaults' => [
            'request_type' => env('ECARSTRADE_SEARCH_DEFAULT_REQUEST_TYPE', 'cars'),
            'auction_type' => env('ECARSTRADE_SEARCH_DEFAULT_AUCTION_TYPE', 'search'),
        ],
    ],
    'import' => [
        'enabled' => filter_var(env('ECARSTRADE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'sync_limit' => (int) env('ECARSTRADE_SYNC_LIMIT', 20),
        'sync_every_minutes' => (int) env('ECARSTRADE_SYNC_EVERY_MINUTES', 30),
        'auto_publish' => filter_var(env('ECARSTRADE_AUTO_PUBLISH', false), FILTER_VALIDATE_BOOLEAN),
        'fetch_details' => filter_var(env('ECARSTRADE_FETCH_DETAILS', true), FILTER_VALIDATE_BOOLEAN),
        'detail_delay_ms' => (int) env('ECARSTRADE_DETAIL_DELAY_MS', 150),
        'publish_media' => filter_var(env('ECARSTRADE_PUBLISH_MEDIA', true), FILTER_VALIDATE_BOOLEAN),
        'publish_documents' => filter_var(env('ECARSTRADE_PUBLISH_DOCUMENTS', true), FILTER_VALIDATE_BOOLEAN),
        'margin_min' => (float) env('ECARSTRADE_MARGIN_MIN', 2000),
        'margin_max' => (float) env('ECARSTRADE_MARGIN_MAX', 3000),
        'budget_max' => (float) env('ECARSTRADE_IMPORT_BUDGET_MAX', 150000),
        'year_min' => (int) env('ECARSTRADE_IMPORT_YEAR_MIN', 2005),
        'makes' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('ECARSTRADE_IMPORT_MAKES', 'BMW,Mercedes,Peugeot,Renault,Volkswagen,Audi,Toyota'))
        ))),
    ],
    'selectors' => [
        'csrf' => [
            "string(//form[@id='authform-popup']//input[@name='_token'][1]/@value)",
            "string(//form[@name='authform']//input[@name='_token'][1]/@value)",
            "string(//form//input[@name='_token'][1]/@value)",
            "string(//input[contains(@name, 'csrf')][1]/@value)",
            "string(//meta[@name='csrf-token'][1]/@content)",
        ],
        'authenticated_markers' => [
            "//a[contains(@href, 'logout')]",
            "//form[contains(@action, 'logout')]",
            "//a[contains(@href, '/bids')]",
            "//*[contains(@class, 'show_logout_form')]",
            "//*[contains(@class, 'user-menu')]",
            "//*[contains(@class, 'account-menu')]",
        ],
    ],
];
