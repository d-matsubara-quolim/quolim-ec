<?php

namespace Tests\Feature\Seasonal;

use Carbon\CarbonImmutable;
use Tests\TestCase;

class ProductPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00:00'));
    }

    public function test_home_lists_open_campaigns(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('クリスマスケーキ・オードブル')
            ->assertSee(route('seasonal.products.index', 'xmas-2026'), false);
    }

    public function test_home_hides_closed_campaigns(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-22 00:00:00'));

        $this->get('/')->assertOk()->assertSee('現在受付中のWeb予約はありません。');
    }

    public function test_list_shows_all_products_with_price_and_quantity_limit(): void
    {
        $response = $this->get('/seasonal/xmas-2026')->assertOk();

        foreach ([
            [7274, 'シャンティ・ショコラ・フレーズ', '¥4,900', '限定 50'],
            [5298, 'アルブル・ド・ノエル', '¥4,000', '限定 20'],
            [5300, 'シャンティ・フレーズ', '¥4,400', '限定 50'],
            [8866, 'titaのオードブル', '¥3,620', null],
            [3833, 'フライドチキン（４個）', '¥650', null],
            [8767, '気まぐれキッシュ（ホール）', '¥2,290', null],
            [8768, '気まぐれキッシュ（1/4カット）', '¥650', null],
        ] as [$id, $name, $price, $note]) {
            $response->assertSee($name)->assertSee($price)
                ->assertSee("name=\"quantities[{$id}]\"", false)
                ->assertSee('min="0" max="10"', false);
            if ($note !== null) {
                $response->assertSee($note);
            }
        }

        $response->assertSeeInOrder(['シャンティ・ショコラ・フレーズ', 'アルブル・ド・ノエル', 'シャンティ・フレーズ', 'titaのオードブル']);
        $response->assertSeeInOrder(['フラットリア チタ', 'でのお受け取りのみ']);
        $response->assertSee('サイエンス パティスリー ササ')->assertSee('フラットリア チタ');
        $response->assertDontSee('price]', false);
        $response->assertDontSee('商品ID');
        $response->assertSeeInOrder(['¥3,620', 'でのお受け取りのみ']);
    }

    public function test_detail_page(): void
    {
        $this->get('/seasonal/xmas-2026/products/8866')
            ->assertOk()
            ->assertSee('titaのオードブル')
            ->assertDontSee('商品ID')
            ->assertSee('¥3,620')
            ->assertSee('https://cdn.quolim.co.jp/img/tita/menu/set_004.png')
            ->assertSeeInOrder(['受取店舗', 'フラットリア チタ'])
            ->assertDontSee('サイエンス パティスリー ササ')
            ->assertDontSee('product-detail__description', false);
    }

    public function test_detail_page_shows_escaped_description_with_line_breaks(): void
    {
        config(['seasonal.xmas-2026.products.3.description' => "<b>人気No.1</b>\n4〜5人前"]);

        $this->get('/seasonal/xmas-2026/products/8866')
            ->assertOk()
            ->assertSee('&lt;b&gt;人気No.1&lt;/b&gt;<br />'."\n".'4〜5人前', false)
            ->assertDontSee('<b>人気No.1</b>', false);
    }

    public function test_form_links_to_privacy_policy(): void
    {
        $this->get('/seasonal/xmas-2026')
            ->assertOk()
            ->assertSee('href="https://quolim.co.jp/privacy-policy.html"', false);
    }

    public function test_unknown_product_or_campaign_is_404(): void
    {
        $this->get('/seasonal/xmas-2026/products/9999')->assertNotFound();
        $this->get('/seasonal/no-such-campaign')->assertNotFound();
    }

    public function test_before_period_shows_opening_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 23:59:59'));

        $this->get('/seasonal/xmas-2026')
            ->assertOk()
            ->assertSee('Web予約の受付は 11月21日 00:00 からです。')
            ->assertDontSee('確認画面へ');
    }

    public function test_period_boundaries_are_inclusive(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 00:00:00'));
        $this->get('/seasonal/xmas-2026')->assertSee('確認画面へ');

        $this->travelTo(CarbonImmutable::parse('2026-12-21 23:59:59'));
        $this->get('/seasonal/xmas-2026')->assertSee('確認画面へ');
    }

    public function test_after_period_shows_closed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-22 00:00:00'));

        $this->get('/seasonal/xmas-2026')->assertOk()->assertSee('Web予約の受付は終了しました。');
        $this->get('/seasonal/xmas-2026/products/7274')->assertOk()->assertSee('Web予約の受付は終了しました。');
    }

    public function test_period_can_be_changed_by_configuration(): void
    {
        config(['seasonal.xmas-2026.opens_at' => '2026-12-01 00:00:00']);

        $this->get('/seasonal/xmas-2026')->assertSee('Web予約の受付は 12月1日 00:00 からです。');
    }
}
