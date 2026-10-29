@extends('layouts.app')

@section('title', 'ご予約を承りました')

@section('content')
    <h1 class="page-title">ご予約を承りました</h1>

    <div class="reservation-number">
        <p class="reservation-number__label">予約番号</p>
        <p class="reservation-number__value">{{ $completed->slipNo }}</p>
        <p>お受け取りの際に、予約番号またはお名前をお伝えください。</p>
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
@endsection
