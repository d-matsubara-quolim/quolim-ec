<?php

namespace App\Logging;

use App\LineWorks\LineWorksClient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PDOException;
use Throwable;

/**
 * ERROR 以上のログを LINE WORKS のチャネルに通知する。
 *
 * SQL のエラーメッセージにはバインド値（お客様の氏名・電話番号）が含まれるため、
 * DB の例外は SQLSTATE だけを送る。通知に失敗してもリクエストの処理は止めない。
 */
final class LineWorksHandler extends AbstractProcessingHandler
{
    private static bool $sending = false;

    public function __construct(
        private readonly LineWorksClient $client,
        private readonly ?string $channelId,
        private readonly int $throttleSeconds,
        Level $level = Level::Error,
    ) {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        if (blank($this->channelId) || self::$sending || ! $this->client->isConfigured()) {
            return;
        }

        self::$sending = true;

        try {
            // 障害時に同じエラーが連続しても、一定時間内は 1 回だけ通知する
            if (! Cache::add('lineworks:error-notified:'.sha1($this->fingerprint($record)), true, $this->throttleSeconds)) {
                return;
            }

            if (! $this->client->sendMessage($this->channelId, $this->message($record))) {
                error_log('LINE WORKS error notification was not delivered.');
            }
        } catch (Throwable $e) {
            error_log('LINE WORKS error notification failed: '.$e::class);
        } finally {
            self::$sending = false;
        }
    }

    private function message(LogRecord $record): string
    {
        $exception = $record->context['exception'] ?? null;
        $exception = $exception instanceof Throwable ? $exception : null;

        $lines = [
            sprintf('【%s】エラーが発生しました（%s）', config('app.name'), app()->environment()),
            '日時: '.$record->datetime->format('Y-m-d H:i:s'),
            '内容: '.($exception !== null && self::isDatabaseError($exception)
                ? self::summarize($exception)
                : Str::limit($record->message, 300)),
        ];

        foreach ($record->context as $key => $value) {
            if ($key !== 'exception' && is_scalar($value)) {
                $lines[] = $key.': '.Str::limit((string) $value, 200);
            }
        }

        if ($exception !== null) {
            $lines[] = '例外: '.$exception::class;
            $lines[] = '詳細: '.self::summarize($exception);
            $lines[] = '場所: '.Str::after($exception->getFile(), base_path().DIRECTORY_SEPARATOR).':'.$exception->getLine();
        }

        if (! app()->runningInConsole() && app()->bound('request')) {
            $request = app('request');
            $lines[] = 'URL: '.$request->method().' /'.ltrim($request->path(), '/');
        }

        return implode("\n", $lines);
    }

    private function fingerprint(LogRecord $record): string
    {
        $exception = $record->context['exception'] ?? null;

        return $exception instanceof Throwable
            ? $exception::class.'|'.$exception->getFile().'|'.$exception->getLine()
            : $record->message.'|'.json_encode(array_filter($record->context, 'is_scalar'));
    }

    private static function summarize(Throwable $e): string
    {
        if (self::isDatabaseError($e)) {
            $sqlState = $e instanceof QueryException ? ($e->errorInfo[0] ?? $e->getCode()) : $e->getCode();
            $driverCode = $e instanceof QueryException ? ($e->errorInfo[1] ?? '-') : '-';

            return "Database error (SQLSTATE {$sqlState}, driver code {$driverCode})";
        }

        return Str::limit($e->getMessage(), 300);
    }

    private static function isDatabaseError(Throwable $e): bool
    {
        return $e instanceof QueryException || $e instanceof PDOException;
    }
}
