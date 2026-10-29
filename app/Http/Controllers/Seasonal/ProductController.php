<?php

namespace App\Http\Controllers\Seasonal;

use App\Http\Controllers\Controller;
use App\Seasonal\Campaign;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * 商品一覧と予約フォーム（商品・個数、受取店舗・日時、連絡先）。
     */
    public function index(Campaign $campaign): View|Response
    {
        if (! $campaign->isOpen()) {
            return response()->view('seasonal.reservations.closed', ['campaign' => $campaign]);
        }

        return view('seasonal.products.index', ['campaign' => $campaign]);
    }

    public function show(Campaign $campaign, int $product): View|Response
    {
        if (! $campaign->isOpen()) {
            return response()->view('seasonal.reservations.closed', ['campaign' => $campaign]);
        }

        return view('seasonal.products.show', [
            'campaign' => $campaign,
            'product' => $campaign->product($product) ?? abort(404),
        ]);
    }
}
