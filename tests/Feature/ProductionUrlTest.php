<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        // boot() で有効にした本番用の制限を他のテストに持ち越さない
        DB::prohibitDestructiveCommands(false);

        parent::tearDown();
    }

    public function test_production_urls_use_app_url_even_when_host_is_the_load_balancer(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00:00'));
        config(['app.url' => 'https://reserve.example.com/']);
        $this->app['env'] = 'production';
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $this->get('http://internal-quolim-ec-123456.ap-northeast-1.elb.amazonaws.com/')
            ->assertOk()
            ->assertSee('href="https://reserve.example.com/css/app.css?v=', false)
            ->assertSee('href="https://reserve.example.com/seasonal/xmas-2026"', false)
            ->assertDontSee('elb.amazonaws.com', false);
    }

    public function test_urls_follow_the_request_outside_production(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00:00'));

        $this->get('http://localhost:8090/')
            ->assertOk()
            ->assertSee('href="http://localhost:8090/css/app.css?v=', false);
    }
}
