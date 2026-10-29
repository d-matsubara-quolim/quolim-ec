<?php

namespace Tests\Feature\Pos;

use App\Pos\Exceptions\PosRegistrationException;
use App\Pos\PosOrderItem;
use App\Pos\PosReservation;
use App\Pos\PosReservationRegistrar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\UsesPosTestDatabase;
use Tests\TestCase;

class PosReservationRegistrarTest extends TestCase
{
    use UsesPosTestDatabase;

    private function reservation(array $overrides = []): PosReservation
    {
        return new PosReservation(...array_merge([
            'brandId' => 12,
            'slipDate' => CarbonImmutable::parse('2026-12-24'),
            'startTime' => '17:00',
            'endTime' => '18:00',
            'customerName' => '笹 泉子',
            'customerNameKana' => 'ささ いずみこ',
            'tel' => '090-1234-5678',
            'customerRequest' => 'ろうそく多めで',
            'items' => [
                new PosOrderItem(productId: 7298, amount: 2, price: 4900),
                new PosOrderItem(productId: 8866, amount: 1, price: 3620),
            ],
        ], $overrides));
    }

    private function register(?PosReservation $reservation = null)
    {
        return $this->app->make(PosReservationRegistrar::class)->register($reservation ?? $this->reservation());
    }

    public function test_registers_slip_like_the_legacy_import(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:15:30'));

        $result = $this->register();

        $slip = $this->posTable('P_SLIP_TRN')->where('SLIP_ID', $result->slipId)->first();
        $this->assertSame('261224121000', $result->slipNo);
        $this->assertSame($result->slipNo, $slip->SLIP_NO);
        $this->assertSame(12, $slip->BRAND_ID);
        $this->assertSame(1, $slip->BRAND_BRANCH_NO);
        $this->assertSame('2026-12-24', $slip->SLIP_DATE);
        $this->assertSame('17:00:00', $slip->START_TIME);
        $this->assertSame('18:00:00', $slip->END_TIME);
        $this->assertSame('17:00:00', $slip->RESERVATION_START_LIMIT);
        $this->assertSame('18:00:00', $slip->RESERVATION_END_LIMIT);
        $this->assertSame('2026-12-24 17:00:00', $slip->RESERVED_DATE);
        $this->assertSame('笹 泉子', $slip->CUSTOMER_NAME);
        $this->assertSame('ささ いずみこ', $slip->CUSTOMER_NAME_KANA);
        $this->assertSame('090-1234-5678', $slip->TEL);
        $this->assertSame('【お客様からのご要望】ろうそく多めで', $slip->NOTES);
        $this->assertSame(1, $slip->CUSTOMER_BASE_ID);
        $this->assertSame(1, $slip->ADULT_NUMBER);
        $this->assertSame(0, $slip->CHILD_NUMBER);
        $this->assertSame(0, $slip->ADULT_NUMBER_LIMIT);
        $this->assertSame(0, $slip->CHILD_NUMBER_LIMIT);
        $this->assertSame(1, $slip->STATUS);
        $this->assertSame(1, $slip->RESERVATION_STATUS);
        $this->assertSame(2, $slip->DAYPART_KBN);
        $this->assertSame(0, $slip->IS_DELETED);
        $this->assertNull($slip->DISCOUNT_ID);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $slip->SELF_KEY);
        $this->assertSame(6361, $slip->INSERTED_BY_ID);
        $this->assertSame(6361, $slip->UPDATED_BY_ID);
        $this->assertSame(4900 * 2 + 3620, $slip->TOTAL_PRICE);
        $this->assertSame(4900 * 2 + 3620, $slip->TOTAL_FIXED_PRICE);

        $room = $this->posTable('P_SLIP_ROOM_TRN')->where('SLIP_ID', $result->slipId)->sole();
        $this->assertSame(165, $room->ROOM_ID);

        $details = $this->posTable('P_SLIP_DETAIL_TRN')->where('SLIP_ID', $result->slipId)->orderBy('SLIP_DETAIL_ID')->get();
        $this->assertSame([5, 7, 8], $details->pluck('GROUP_PRODUCT_ID')->all());
        // ドリンク（空の注文）・フード・未使用
        $this->assertSame([1, 1, 0], $details->pluck('AMOUNT')->all());
        $this->assertSame([null, 4900 * 2 + 3620, null], $details->pluck('PRICE')->all());
        $this->assertSame([null, 4900 * 2 + 3620, null], $details->pluck('FIXED_PRICE')->all());
        $this->assertSame([null, 4900 * 2 + 3620, null], $details->pluck('NOT_DISCOUNTED_PRICE')->all());

        $orders = $this->posTable('P_ORDER_TRN')->orderBy('ORDER_ID')->get();
        $this->assertSame([$details[0]->SLIP_DETAIL_ID, $details[1]->SLIP_DETAIL_ID], $orders->pluck('SLIP_DETAIL_ID')->all());
        $this->assertSame(['2026-11-25 10:15:30', '2026-11-25 10:15:30'], $orders->pluck('ORDER_DATE')->all());
        $this->assertSame([0, 0], $orders->pluck('IS_DELETED')->all());

        $orderDetails = $this->posTable('P_ORDER_DETAIL_TRN')->orderBy('ORDER_DETAIL_ID')->get();
        $this->assertCount(2, $orderDetails);
        $this->assertSame([$orders[1]->ORDER_ID, $orders[1]->ORDER_ID], $orderDetails->pluck('ORDER_ID')->all());
        $this->assertSame([7298, 8866], $orderDetails->pluck('PRODUCT_ID')->all());
        $this->assertSame([2, 1], $orderDetails->pluck('AMOUNT')->all());
        $this->assertSame([4900, 3620], $orderDetails->pluck('FIXED_PRICE')->all());
        $this->assertSame([4900, 3620], $orderDetails->pluck('PRICE')->all());
        $this->assertSame([8, 8], $orderDetails->pluck('TAX_RATE')->all());
        $this->assertSame([1, 1], $orderDetails->pluck('STATUS')->all());
        $this->assertSame([0, 0], $orderDetails->pluck('IS_DELETED')->all());
    }

    public function test_records_update_history_like_the_legacy_import(): void
    {
        $result = $this->register();

        $details = $this->posTable('P_SLIP_DETAIL_TRN')->orderBy('SLIP_DETAIL_ID')->pluck('SLIP_DETAIL_ID');
        $room = $this->posTable('P_SLIP_ROOM_TRN')->value('SLIP_ROOM_ID');
        $orders = $this->posTable('P_ORDER_TRN')->orderBy('ORDER_ID')->pluck('ORDER_ID');
        $orderDetails = $this->posTable('P_ORDER_DETAIL_TRN')->orderBy('ORDER_DETAIL_ID')->pluck('ORDER_DETAIL_ID');

        $history = $this->posTable('P_UPDATED_TRN')->orderBy('UPDATED_ID')->get()
            ->map(fn ($row) => [$row->TABLE_NAME, $row->KEY_VALUE, $row->SLIP_ID])
            ->all();

        $this->assertSame([
            ['P_SLIP_TRN', $result->slipId, $result->slipId],
            ['P_SLIP_ROOM_TRN', $room, $result->slipId],
            ['P_SLIP_DETAIL_TRN', $details[0], $result->slipId],
            ['P_SLIP_DETAIL_TRN', $details[1], $result->slipId],
            ['P_SLIP_DETAIL_TRN', $details[2], $result->slipId],
            // 旧処理の挙動：注文の履歴の SLIP_ID は、明細のない注文は 0、明細のある注文は注文ID
            ['P_ORDER_TRN', $orders[0], 0],
            ['P_ORDER_TRN', $orders[1], $orders[1]],
            ['P_ORDER_DETAIL_TRN', $orderDetails[0], $orders[1]],
            ['P_ORDER_DETAIL_TRN', $orderDetails[1], $orders[1]],
        ], $history);

        $row = $this->posTable('P_UPDATED_TRN')->first();
        $this->assertSame('', $row->COLUMN_NAME);
        $this->assertSame('', $row->BEFORE_VALUE);
        $this->assertSame('', $row->AFTER_VALUE);
        $this->assertSame(6361, $row->OPERATOR);
        $this->assertNull($row->ORDER_ID);
    }

    public function test_numbers_slips_per_date_and_brand(): void
    {
        $first = $this->register();
        $second = $this->register();
        $otherBrand = $this->register($this->reservation(['brandId' => 7, 'items' => [new PosOrderItem(7274, 1, 4900)]]));
        $otherDate = $this->register($this->reservation(['slipDate' => CarbonImmutable::parse('2026-12-25')]));

        $this->assertSame('261224121000', $first->slipNo);
        $this->assertSame('261224121001', $second->slipNo);
        $this->assertSame('261224071000', $otherBrand->slipNo);
        $this->assertSame('261225121000', $otherDate->slipNo);
    }

    public function test_daypart_is_noon_before_17(): void
    {
        $result = $this->register($this->reservation(['startTime' => '16:00', 'endTime' => '17:00']));

        $this->assertSame(1, $this->posTable('P_SLIP_TRN')->where('SLIP_ID', $result->slipId)->value('DAYPART_KBN'));
    }

    public function test_drinks_go_to_the_drink_order_with_10_percent_tax(): void
    {
        $this->register($this->reservation(['items' => [
            new PosOrderItem(productId: 2781, amount: 1, price: 500),
            new PosOrderItem(productId: 7298, amount: 1, price: 4900),
        ]]));

        $orders = $this->posTable('P_ORDER_TRN')->orderBy('ORDER_ID')->pluck('ORDER_ID');
        $drink = $this->posTable('P_ORDER_DETAIL_TRN')->where('PRODUCT_ID', 2781)->sole();
        $food = $this->posTable('P_ORDER_DETAIL_TRN')->where('PRODUCT_ID', 7298)->sole();

        $this->assertSame($orders[0], $drink->ORDER_ID);
        $this->assertSame(10, $drink->TAX_RATE);
        $this->assertSame($orders[1], $food->ORDER_ID);
        $this->assertSame(8, $food->TAX_RATE);
        $this->assertSame(5400, $this->posTable('P_SLIP_TRN')->value('TOTAL_PRICE'));
    }

    public function test_empty_optional_fields_are_stored_as_null_and_notes_keep_prefix(): void
    {
        $this->register($this->reservation(['customerNameKana' => '', 'tel' => '', 'customerRequest' => '']));

        $slip = $this->posTable('P_SLIP_TRN')->first();
        $this->assertNull($slip->CUSTOMER_NAME_KANA);
        $this->assertNull($slip->TEL);
        $this->assertSame('【お客様からのご要望】', $slip->NOTES);
    }

    public function test_rolls_back_everything_when_a_later_insert_fails(): void
    {
        // 存在しない商品ID → 注文明細の外部キー違反（伝票・伝票明細・注文の登録後に失敗する）
        $reservation = $this->reservation(['items' => [
            new PosOrderItem(productId: 7298, amount: 1, price: 4900),
            new PosOrderItem(productId: 99999, amount: 1, price: 100),
        ]]);

        try {
            $this->register($reservation);
            $this->fail('PosRegistrationException was not thrown.');
        } catch (PosRegistrationException $e) {
            $this->assertStringNotContainsString('笹', $e->getMessage());
            $this->assertStringNotContainsString('090-1234-5678', $e->getMessage());
        }

        foreach (['P_SLIP_TRN', 'P_SLIP_ROOM_TRN', 'P_SLIP_DETAIL_TRN', 'P_ORDER_TRN', 'P_ORDER_DETAIL_TRN', 'P_UPDATED_TRN'] as $table) {
            $this->assertSame(0, $this->posTable($table)->count(), "{$table} should be empty after rollback.");
        }
    }

    public function test_concurrent_registrations_are_each_complete(): void
    {
        $processes = 8;
        $startAt = microtime(true) + 2;
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pos',
            'POS_DB_HOST' => config('database.connections.pos.host'),
            'POS_DB_PORT' => (string) config('database.connections.pos.port'),
            'POS_DB_DATABASE' => config('database.connections.pos.database'),
            'POS_DB_USERNAME' => config('database.connections.pos.username'),
            'POS_DB_PASSWORD' => config('database.connections.pos.password'),
            'LOG_STACK' => 'single',
        ];

        $results = Process::pool(function ($pool) use ($processes, $startAt, $env) {
            for ($i = 0; $i < $processes; $i++) {
                $pool->path(base_path())->env($env)->timeout(120)
                    ->command(['php', 'tests/Fixtures/register_reservation.php', (string) $startAt, (string) $i]);
            }
        })->start()->wait();

        $slipIds = [];
        foreach ($results as $result) {
            $this->assertTrue($result->successful(), $result->errorOutput().$result->output());
            $slipIds[] = json_decode($result->output(), true)['slip_id'];
        }

        $this->assertCount($processes, array_unique($slipIds));
        $this->assertSame($processes, $this->posTable('P_SLIP_TRN')->count());
        foreach ($slipIds as $slipId) {
            $details = $this->posTable('P_SLIP_DETAIL_TRN')->where('SLIP_ID', $slipId)->pluck('SLIP_DETAIL_ID');
            $this->assertCount(3, $details);
            $this->assertSame(1, $this->posTable('P_SLIP_ROOM_TRN')->where('SLIP_ID', $slipId)->count());
            $this->assertSame(2, $this->posTable('P_ORDER_TRN')->whereIn('SLIP_DETAIL_ID', $details)->count());
            $this->assertSame(4900 * 2 + 3620, $this->posTable('P_SLIP_TRN')->where('SLIP_ID', $slipId)->value('TOTAL_PRICE'));
        }
        $this->assertSame($processes * 2, $this->posTable('P_ORDER_DETAIL_TRN')->count());
    }
}
