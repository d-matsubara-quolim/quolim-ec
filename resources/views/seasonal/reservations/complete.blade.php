@extends('layouts.app')

@section('title', 'ご予約を承りました')

@section('content')
<div class="narrow">
    <ol class="progress" aria-label="ご予約の手順">
        <li class="progress__item is-done">入力</li>
        <li class="progress__item is-done">確認</li>
        <li class="progress__item is-current" aria-current="step">完了</li>
    </ol>

    <div class="complete">
        <span class="complete__icon" aria-hidden="true">✓</span>
        <h1 class="page-title">ご予約を承りました</h1>
        <div class="reservation-number">
            <p class="reservation-number__label">予約番号</p>
            <p class="reservation-number__value">{{ $completed->slipNo }}</p>
            <p class="reservation-number__note">お受け取りの際に、予約番号またはお名前をお伝えください。<br>この画面を保存しておくと便利です。</p>
        </div>
    </div>

    @include('seasonal.reservations._summary', [
        'campaign' => $campaign,
        'lines' => $campaign->orderLines($completed->quantities),
        'total' => $campaign->orderTotal($completed->quantities),
        'store' => $campaign->store($completed->storeCode),
        'date' => $completed->date,
        'timeSlot' => $completed->timeSlot,
    ])

    <p class="notice">
        お支払いは、お受け取りの際に店舗でお願いいたします。<br>
        ご予約の変更・キャンセルは、受取店舗までお問い合わせください。
    </p>

    <div class="actions">
        <a href="{{ route('home') }}" class="button">トップへ戻る</a>
    </div>
</div>
@endsection
