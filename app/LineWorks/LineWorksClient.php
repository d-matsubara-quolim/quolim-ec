<?php

namespace App\LineWorks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * LINE WORKS Bot からチャネルへのメッセージ送信（erp-quolim の LineWorksService::sendMessage と同じ方式）。
 */
final class LineWorksClient
{
    private const string TOKEN_URL = 'https://auth.worksmobile.com/oauth2/v2.0/token';

    private const string TOKEN_CACHE_KEY = 'lineworks:access-token';

    private const int TOKEN_CACHE_SECONDS = 3500;

    public function isConfigured(): bool
    {
        foreach (['client_id', 'client_secret', 'service_account', 'private_key', 'bot_id'] as $key) {
            if (blank(config("lineworks.{$key}"))) {
                return false;
            }
        }

        return true;
    }

    public function sendMessage(string $channelId, string $text): bool
    {
        $token = $this->accessToken();
        if ($token === null) {
            return false;
        }

        $botId = config('lineworks.bot_id');
        $response = Http::withToken($token)
            ->connectTimeout(3)
            ->timeout(5)
            ->post("https://www.worksapis.com/v1.0/bots/{$botId}/channels/{$channelId}/messages", [
                'content' => ['type' => 'text', 'text' => $text],
            ]);

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }

        return $response->status() === 201;
    }

    private function accessToken(): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(5)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt(),
                'client_id' => config('lineworks.client_id'),
                'client_secret' => config('lineworks.client_secret'),
                'scope' => 'bot',
            ]);

        $token = $response->successful() ? $response->json('access_token') : null;
        if (! is_string($token) || $token === '') {
            return null;
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_CACHE_SECONDS);

        return $token;
    }

    private function jwt(): string
    {
        $now = time();
        $input = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.self::base64Url(json_encode([
                'iss' => config('lineworks.client_id'),
                'sub' => config('lineworks.service_account'),
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

        $key = openssl_pkey_get_private(str_replace('\n', "\n", (string) config('lineworks.private_key')));
        if ($key === false || ! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('LINE WORKS private key is invalid.');
        }

        return $input.'.'.self::base64Url($signature);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
