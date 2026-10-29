<?php

namespace App\Http\Controllers\Seasonal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seasonal\ReservationRequest;
use App\Seasonal\Campaign;
use App\Seasonal\Exceptions\ReservationException;
use App\Seasonal\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservations) {}

    public function confirm(ReservationRequest $request, Campaign $campaign): View|Response
    {
        if (! $campaign->isOpen()) {
            return response()->view('seasonal.reservations.closed', ['campaign' => $campaign]);
        }

        $data = $request->toReservationData();

        return view('seasonal.reservations.confirm', [
            'campaign' => $campaign,
            'reservation' => $data,
            'token' => $this->reservations->prepare($request->session(), $campaign, $data),
        ]);
    }

    /**
     * 確認画面の「修正する」。入力内容を入力欄に戻す。
     */
    public function edit(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $this->reservations->pending($request->session(), $campaign, (string) $request->input('token'));

        return redirect()
            ->route('seasonal.products.index', $campaign->slug)
            ->withInput($data?->toArray() ?? []);
    }

    public function store(Request $request, Campaign $campaign): RedirectResponse|View|Response
    {
        $token = (string) $request->input('token');

        try {
            $this->reservations->reserve($request->session(), $campaign, $token);
        } catch (ReservationException $e) {
            if ($e->isClosed()) {
                return response()->view('seasonal.reservations.closed', ['campaign' => $campaign]);
            }
            if ($e->isExpired()) {
                return redirect()
                    ->route('seasonal.products.index', $campaign->slug)
                    ->with('error', '確認画面の有効期限が切れました。お手数ですが、もう一度ご入力ください。');
            }

            $data = $this->reservations->pending($request->session(), $campaign, $token);
            if ($data === null) {
                return redirect()->route('seasonal.products.index', $campaign->slug);
            }

            return view('seasonal.reservations.confirm', [
                'campaign' => $campaign,
                'reservation' => $data,
                'token' => $token,
                'error' => $e->isBusy()
                    ? 'ただいま処理中です。少し時間をおいてから、もう一度「予約する」を押してください。'
                    : '申し訳ございません。ご予約を登録できませんでした。時間をおいて、もう一度「予約する」を押してください。',
            ]);
        }

        return redirect()->route('seasonal.reservations.complete', $campaign->slug);
    }

    public function complete(Request $request, Campaign $campaign): View|RedirectResponse
    {
        $completed = $this->reservations->completed($request->session(), $campaign);
        if ($completed === null) {
            return redirect()->route('seasonal.products.index', $campaign->slug);
        }

        return view('seasonal.reservations.complete', ['campaign' => $campaign, 'completed' => $completed]);
    }
}
