<?php

namespace Tests\Feature\Seasonal;

use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsesPosTestDatabase;
use Tests\TestCase;

class ReservationFlowTest extends TestCase
{
    use UsesPosTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00:00'));
    }

    private function input(array $overrides = []): array
    {
        return array_replace([
            'quantities' => [7274 => 2, 5298 => 0, 8866 => 1, 3833 => 3],
            'store' => '12',
            'date' => '2026-12-24',
            'time_slot' => '17:00-18:00',
            'name' => '笹 泉子',
            'kana' => 'ささ いずみこ',
            'tel' => '090-1234-5678',
            'note' => 'ろうそく多めで',
        ], $overrides);
    }

    private function confirm(array $input): TestResponse
    {
        return $this->post('/seasonal/xmas-2026/confirm', $input);
    }

    private function tokenFrom(TestResponse $response): string
    {
        preg_match('/name="token" value="([^"]+)"/', $response->getContent(), $matches);

        return $matches[1] ?? $this->fail('token not found on the confirmation page');
    }

    public function test_multiple_products_reach_the_confirmation_page_with_server_side_prices(): void
    {
        $response = $this->confirm($this->input([
            'price' => 1,
            'quantities' => [7274 => 2, 8866 => 1, 3833 => 3],
        ]));

        $response->assertOk()
            ->assertSee('まだご予約は完了していません。')
            ->assertSeeInOrder(['シャンティ・ショコラ・フレーズ', '¥4,900', '2台', '¥9,800'])
            ->assertSeeInOrder(['titaのオードブル', '¥3,620', '1セット', '¥3,620'])
            ->assertSeeInOrder(['フライドチキン（４個）', '¥650', '3セット', '¥1,950'])
            ->assertSee('¥15,370')
            ->assertSee('フラットリア チタ')
            ->assertSee('12月24日（木）')
            ->assertSee('17:00〜18:00')
            ->assertSee('ろうそく多めで');

        $this->assertSame(0, $this->posTable('P_SLIP_TRN')->count());
    }

    public function test_full_reservation_registers_to_pos_and_shows_reservation_number(): void
    {
        $token = $this->tokenFrom($this->confirm($this->input()));

        $this->post('/seasonal/xmas-2026/reserve', ['token' => $token])
            ->assertRedirect('/seasonal/xmas-2026/complete');

        $this->get('/seasonal/xmas-2026/complete')
            ->assertOk()
            ->assertSee('ご予約を承りました')
            ->assertSee('261224121000')
            ->assertSee('¥15,370')
            ->assertDontSee('090-1234-5678');

        $slip = $this->posTable('P_SLIP_TRN')->sole();
        $this->assertSame('261224121000', $slip->SLIP_NO);
        $this->assertSame(12, $slip->BRAND_ID);
        $this->assertSame(15370, $slip->TOTAL_PRICE);

        // 店舗 12 では店舗別の商品IDに変換して登録する（7274 → 7298）
        $this->assertSame(
            [7298 => [2, 4900], 8866 => [1, 3620], 3833 => [3, 650]],
            $this->posTable('P_ORDER_DETAIL_TRN')->orderBy('ORDER_DETAIL_ID')->get()
                ->mapWithKeys(fn ($row) => [$row->PRODUCT_ID => [$row->AMOUNT, $row->PRICE]])->all(),
        );
    }

    public function test_store_07_uses_its_own_product_ids_and_19_oclock_slot(): void
    {
        $token = $this->tokenFrom($this->confirm($this->input([
            'quantities' => [7274 => 1, 5298 => 1],
            'store' => '07',
            'time_slot' => '18:00-19:00',
        ])));

        $this->post('/seasonal/xmas-2026/reserve', ['token' => $token])->assertRedirect('/seasonal/xmas-2026/complete');

        $this->assertSame('261224071000', $this->posTable('P_SLIP_TRN')->value('SLIP_NO'));
        $this->assertSame([7274, 5298], $this->posTable('P_ORDER_DETAIL_TRN')->orderBy('ORDER_DETAIL_ID')->pluck('PRODUCT_ID')->all());
        $this->assertSame(161, $this->posTable('P_SLIP_ROOM_TRN')->value('ROOM_ID'));
    }

    public function test_quantity_limits(): void
    {
        $this->confirm($this->input(['quantities' => [7274 => 11]]))->assertSessionHasErrors('quantities.7274');
        $this->confirm($this->input(['quantities' => [7274 => -1]]))->assertSessionHasErrors('quantities.7274');
        $this->confirm($this->input(['quantities' => [7274 => 'a']]))->assertSessionHasErrors('quantities.7274');
        $this->confirm($this->input(['quantities' => [7274 => 0, 8866 => 0]]))->assertSessionHasErrors(['quantities' => 'ご予約の商品と個数を選択してください。']);
        $this->confirm($this->input(['quantities' => [12345 => 1]]))->assertSessionHasErrors(['quantities' => 'ご予約の商品が正しくありません。']);
        $this->confirm($this->input(['quantities' => [7274 => 10, 8866 => 10]]))->assertOk();
    }

    public function test_sasa_cannot_hand_over_tita_products(): void
    {
        $this->confirm($this->input(['store' => '07', 'quantities' => [7274 => 1, 8866 => 1]]))
            ->assertSessionHasErrors(['store' => '「titaのオードブル」は「サイエンス パティスリー ササ」ではお受け取りできません。']);
    }

    public function test_pickup_store_date_and_time_slot_must_exist_for_the_store(): void
    {
        $this->confirm($this->input(['store' => '99']))->assertSessionHasErrors('store');
        $this->confirm($this->input(['date' => '2026-12-26']))->assertSessionHasErrors('date');
        // 18:00-19:00 はササのみ
        $this->confirm($this->input(['time_slot' => '18:00-19:00']))->assertSessionHasErrors('time_slot');
        $this->confirm($this->input(['time_slot' => '11:00-12:00']))->assertSessionHasErrors('time_slot');
    }

    public function test_contact_validation(): void
    {
        $this->confirm($this->input(['name' => '']))->assertSessionHasErrors('name');
        $this->confirm($this->input(['name' => str_repeat('あ', 21)]))->assertSessionHasErrors('name');
        $this->confirm($this->input(['kana' => '']))->assertSessionHasErrors('kana');
        $this->confirm($this->input(['kana' => str_repeat('あ', 21)]))->assertSessionHasErrors('kana');
        $this->confirm($this->input(['tel' => '']))->assertSessionHasErrors('tel');
        $this->confirm($this->input(['tel' => '090-1234-567']))->assertSessionHasErrors('tel');
        $this->confirm($this->input(['note' => str_repeat('あ', 151)]))->assertSessionHasErrors('note');
        $this->confirm($this->input(['name' => '笹😀']))->assertSessionHasErrors('name');

        $this->confirm($this->input(['name' => str_repeat('あ', 20), 'note' => '']))->assertOk()->assertSee('（ご要望なし）');
    }

    public function test_full_width_phone_number_is_normalized(): void
    {
        $this->confirm($this->input(['tel' => '０６０ー１２３４ー５６７８']))->assertOk()->assertSee('060-1234-5678');
    }

    public function test_html_in_input_is_escaped(): void
    {
        $this->confirm($this->input(['note' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_duplicate_submission_registers_only_once(): void
    {
        $token = $this->tokenFrom($this->confirm($this->input()));

        $this->post('/seasonal/xmas-2026/reserve', ['token' => $token])->assertRedirect('/seasonal/xmas-2026/complete');
        $this->post('/seasonal/xmas-2026/reserve', ['token' => $token])->assertRedirect('/seasonal/xmas-2026/complete');

        $this->assertSame(1, $this->posTable('P_SLIP_TRN')->count());
        $this->assertSame(3, $this->posTable('P_ORDER_DETAIL_TRN')->count());
        $this->get('/seasonal/xmas-2026/complete')->assertSee('261224121000');
    }

    public function test_unknown_token_does_not_register(): void
    {
        $this->confirm($this->input());

        $this->post('/seasonal/xmas-2026/reserve', ['token' => 'unknown'])
            ->assertRedirect('/seasonal/xmas-2026')
            ->assertSessionHas('error');

        $this->assertSame(0, $this->posTable('P_SLIP_TRN')->count());
    }

    public function test_reserve_after_period_end_does_not_register(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-21 23:59:00'));
        $token = $this->tokenFrom($this->confirm($this->input()));

        $this->travelTo(CarbonImmutable::parse('2026-12-22 00:00:01'));
        $this->post('/seasonal/xmas-2026/reserve', ['token' => $token])
            ->assertOk()
            ->assertSee('Web予約の受付は終了しました。');

        $this->assertSame(0, $this->posTable('P_SLIP_TRN')->count());
    }

    public function test_confirm_outside_period_does_not_proceed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00'));

        $this->confirm($this->input())->assertOk()->assertSee('Web予約の受付は 11月21日 00:00 からです。');
    }

    public function test_pos_failure_shows_error_keeps_input_and_can_be_retried(): void
    {
        $token = $this->tokenFrom($this->confirm($this->input()));

        // 商品マスタから消して外部キー違反を起こす
        $this->posTable('P_PRODUCT_MST')->where('PRODUCT_ID', 3833)->delete();

        $this->post('/seasonal/xmas-2026/reserve', ['token' => $token])
            ->assertOk()
            ->assertSee('ご予約を登録できませんでした。')
            ->assertSee('笹 泉子');

        foreach (['P_SLIP_TRN', 'P_SLIP_DETAIL_TRN', 'P_ORDER_TRN', 'P_ORDER_DETAIL_TRN', 'P_UPDATED_TRN'] as $table) {
            $this->assertSame(0, $this->posTable($table)->count(), "{$table} should be empty after rollback.");
        }

        $this->posTable('P_PRODUCT_MST')->insert(['PRODUCT_ID' => 3833, 'PRICE' => 1]);

        $this->post('/seasonal/xmas-2026/reserve', ['token' => $token])->assertRedirect('/seasonal/xmas-2026/complete');
        $this->assertSame(1, $this->posTable('P_SLIP_TRN')->count());
    }

    public function test_edit_returns_to_the_form_with_previous_input(): void
    {
        $token = $this->tokenFrom($this->confirm($this->input()));

        $this->post('/seasonal/xmas-2026/edit', ['token' => $token])->assertRedirect('/seasonal/xmas-2026');

        $this->get('/seasonal/xmas-2026')
            ->assertSee('value="笹 泉子"', false)
            ->assertSee('id="quantity-7274" value="2"', false)
            ->assertSee('name="store" value="12" checked', false)
            ->assertSee('name="date" value="2026-12-24" checked', false)
            ->assertSee('name="time_slot" value="17:00-18:00" checked', false);
    }

    public function test_complete_page_without_reservation_redirects_to_form(): void
    {
        $this->get('/seasonal/xmas-2026/complete')->assertRedirect('/seasonal/xmas-2026');
    }

    public function test_requests_without_csrf_token_are_rejected(): void
    {
        // CSRF 検証はユニットテスト実行中だけ無効になるため、環境名を変えて有効にする
        $this->app['env'] = 'production';

        $this->post('/seasonal/xmas-2026/confirm', $this->input())->assertStatus(419);
    }
}
