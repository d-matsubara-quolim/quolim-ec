<?php

/*
 * 同時予約テスト用：別プロセスから POS 登録を 1 件実行し、結果を JSON で出力する。
 * 引数：開始時刻（UNIX 秒・小数可。全プロセスを同時に開始させるため）、プロセス番号
 */

use App\Pos\PosOrderItem;
use App\Pos\PosReservation;
use App\Pos\PosReservationRegistrar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ($app['config']->get('database.connections.pos.database') !== 'pos_test') {
    fwrite(STDERR, "This script only runs against the test database.\n");
    exit(1);
}

$startAt = (float) ($argv[1] ?? 0);
$wait = $startAt - microtime(true);
if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

$result = $app->make(PosReservationRegistrar::class)->register(new PosReservation(
    brandId: 12,
    slipDate: CarbonImmutable::parse('2026-12-24'),
    startTime: '17:00',
    endTime: '18:00',
    customerName: '同時 予約'.($argv[2] ?? ''),
    customerNameKana: 'どうじ よやく',
    tel: '090-0000-0000',
    customerRequest: '',
    items: [
        new PosOrderItem(productId: 7298, amount: 2, price: 4900),
        new PosOrderItem(productId: 8866, amount: 1, price: 3620),
    ],
));

echo json_encode(['slip_id' => $result->slipId, 'slip_no' => $result->slipNo]);
