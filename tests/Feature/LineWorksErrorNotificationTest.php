<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class LineWorksErrorNotificationTest extends TestCase
{
    private const string CHANNEL_ID = 'fb8ac6d3-8ab7-fb64-2d53-5715b221b206';

    private const string MESSAGE_URL = 'https://www.worksapis.com/v1.0/bots/bot-1/channels/'.self::CHANNEL_ID.'/messages';

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        config([
            'lineworks.client_id' => 'client-1',
            'lineworks.client_secret' => 'secret-1',
            'lineworks.service_account' => 'service@example.com',
            'lineworks.private_key' => str_replace("\n", '\n', $privateKey),
            'lineworks.bot_id' => 'bot-1',
            'logging.channels.lineworks.channel' => self::CHANNEL_ID,
        ]);
    }

    private function fakeLineWorks(): void
    {
        Http::fake([
            'auth.worksmobile.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 86400]),
            'www.worksapis.com/*' => Http::response(null, 201),
        ]);
    }

    public function test_error_log_is_sent_to_channel_with_signed_jwt(): void
    {
        $this->fakeLineWorks();

        Log::channel('lineworks')->error('Seasonal reservation: POS registration failed', [
            'campaign' => 'xmas-2026',
            'store' => '12',
            'error' => 'POS registration failed (Illuminate\Database\QueryException, SQLSTATE 23000, driver code 1452)',
        ]);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://auth.worksmobile.com/oauth2/v2.0/token') {
                return false;
            }
            [$header, $payload, $signature] = explode('.', $request['assertion']);
            $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

            return $request['client_id'] === 'client-1'
                && $request['scope'] === 'bot'
                && $claims['iss'] === 'client-1'
                && $claims['sub'] === 'service@example.com'
                && openssl_verify("{$header}.{$payload}", base64_decode(strtr($signature, '-_', '+/')), $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
        });

        Http::assertSent(fn (Request $request) => $request->url() === self::MESSAGE_URL
            && $request->hasHeader('Authorization', 'Bearer token-1')
            && $request['content']['type'] === 'text'
            && str_contains($request['content']['text'], 'エラーが発生しました（testing）')
            && str_contains($request['content']['text'], '内容: Seasonal reservation: POS registration failed')
            && str_contains($request['content']['text'], 'store: 12')
            && str_contains($request['content']['text'], 'driver code 1452'));
    }

    public function test_database_exception_is_sent_without_sql_or_bindings(): void
    {
        $this->fakeLineWorks();
        $exception = new QueryException(
            'pos',
            'insert into P_SLIP_TRN (CUSTOMER_NAME, TEL) values (?, ?)',
            ['笹 泉子', '090-1234-5678'],
            tap(new PDOException('SQLSTATE[23000]: Integrity constraint violation'), fn ($e) => $e->errorInfo = ['23000', 1452, 'fk']),
        );

        Log::channel('lineworks')->error($exception->getMessage(), ['exception' => $exception]);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== self::MESSAGE_URL) {
                return false;
            }
            $text = $request['content']['text'];

            return str_contains($text, 'SQLSTATE 23000, driver code 1452')
                && str_contains($text, '例外: Illuminate\Database\QueryException')
                && ! str_contains($text, '笹 泉子')
                && ! str_contains($text, '090-1234-5678')
                && ! str_contains($text, 'insert into');
        });
    }

    public function test_same_error_is_notified_once_within_throttle_period(): void
    {
        $this->fakeLineWorks();

        Log::channel('lineworks')->error('Same error', ['store' => '07']);
        Log::channel('lineworks')->error('Same error', ['store' => '07']);
        Log::channel('lineworks')->error('Same error', ['store' => '12']);

        Http::assertSentCount(3); // トークン取得 1 回 + 通知 2 回
    }

    public function test_warning_is_not_notified(): void
    {
        $this->fakeLineWorks();

        Log::channel('lineworks')->warning('Just a warning');

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_when_not_configured(): void
    {
        $this->fakeLineWorks();
        config(['lineworks.private_key' => null]);

        Log::channel('lineworks')->error('Error without credentials');

        Http::assertNothingSent();
    }

    public function test_notification_failure_does_not_break_the_request(): void
    {
        Http::fake(['*' => fn () => throw new RuntimeException('network down')]);

        Log::channel('lineworks')->error('Error while LINE WORKS is down');

        $this->assertTrue(true);
    }
}
