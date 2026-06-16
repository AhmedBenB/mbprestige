<?php

namespace App\Helpers;

class ImageProxy
{
    public static function url(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $encoded = strtr(base64_encode($url), '+/', '-_');
        $sig = substr(hash_hmac('sha256', $url, (string) config('app.key')), 0, 16);

        return route('img.proxy', ['url' => $encoded, 'sig' => $sig]);
    }

    public static function urls(array $urls): array
    {
        return array_values(array_map([static::class, 'url'], array_filter($urls)));
    }
}
