<?php

use App\LineWorks\LineWorksClient;
use Illuminate\Support\Facades\Artisan;

Artisan::command('lineworks:test-notification', function (LineWorksClient $client) {
    $channelId = config('logging.channels.lineworks.channel');

    if (blank($channelId) || ! $client->isConfigured()) {
        $this->error('LINEWORKS_* の設定が足りません（.env を確認してください）。');

        return 1;
    }

    $sent = $client->sendMessage($channelId, sprintf(
        "【%s】エラー通知のテストです（%s）\n日時: %s",
        config('app.name'),
        app()->environment(),
        now()->format('Y-m-d H:i:s'),
    ));

    $sent ? $this->info('送信しました。') : $this->error('送信できませんでした。認証情報と、Bot がチャネルに参加しているかを確認してください。');

    return $sent ? 0 : 1;
})->purpose('LINE WORKS のエラー通知チャネルにテストメッセージを送る');
