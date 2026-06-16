<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageProxyController extends Controller
{
    public function show(Request $request): Response
    {
        $encoded = (string) $request->query('url', '');
        $sig     = (string) $request->query('sig', '');

        if ($encoded === '' || $sig === '') {
            abort(400);
        }

        $url = base64_decode(strtr($encoded, '-_', '+/'));
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400);
        }

        $expected = substr(hash_hmac('sha256', $url, (string) config('app.key')), 0, 16);
        if (!hash_equals($expected, $sig)) {
            abort(403);
        }

        try {
            $httpResponse = Http::timeout(15)
                ->withHeaders([
                    'Accept'     => 'image/*,*/*',
                    'Referer'    => 'https://www.ecarstrade.eu/',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                ])
                ->get($url);

            if (!$httpResponse->successful()) {
                abort(404);
            }

            $contentType = $httpResponse->header('Content-Type') ?: 'image/jpeg';

            return response($httpResponse->body(), 200, [
                'Content-Type'           => $contentType,
                'Cache-Control'          => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Image proxy failed', ['url' => $url, 'error' => $e->getMessage()]);
            abort(404);
        }
    }
}
