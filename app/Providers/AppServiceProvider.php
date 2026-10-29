<?php

namespace App\Providers;

use App\Seasonal\CampaignRepository;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 既存 RDS（POS）に接続しているため、本番ではテーブルを消す系のコマンドを禁止する
        DB::prohibitDestructiveCommands($this->app->isProduction());

        if (filled($proxies = config('app.trusted_proxies'))) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        Route::bind('campaign', fn (string $slug) => $this->app->make(CampaignRepository::class)->find($slug) ?? abort(404));
    }
}
