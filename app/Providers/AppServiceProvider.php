<?php

namespace App\Providers;

use App\Seasonal\CampaignRepository;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
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

        // ALB などを経由すると Host がサイトのドメインと異なることがあるため、本番の URL は APP_URL で作る
        if ($this->app->isProduction()) {
            $appUrl = rtrim((string) config('app.url'), '/');
            URL::forceRootUrl($appUrl);
            URL::forceScheme(parse_url($appUrl, PHP_URL_SCHEME) ?: 'https');
        }

        Route::bind('campaign', fn (string $slug) => $this->app->make(CampaignRepository::class)->find($slug) ?? abort(404));
    }
}
