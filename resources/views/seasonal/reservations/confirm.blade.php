@extends('layouts.app')

@section('title', 'ご予約内容の確認')

@php
    $store = $campaign->store($reservation->storeCode);
@endphp

@section('content')
<div class="narrow">
    <ol class="progress" aria-label="ご予約の手順">
        <li class="progress__item is-done">入力</li>
        <li class="progress__item is-current" aria-current="step">確認</li>
        <li class="progress__item">完了</li>
    </ol>

    <h1 class="page-title">ご予約内容の確認</h1>

    <p class="notice notice--warning">
        まだご予約は完了していません。<br>
        内容をご確認のうえ「予約する」を押し、「ご予約を承りました」と表示されるまでお進みください。
    </p>

    @isset($error)
        <p class="alert" role="alert">{{ $error }}</p>
    @endisset

    @include('seasonal.reservations._summary', [
        'campaign' => $campaign,
        'lines' => $campaign->orderLines($reservation->quantities),
        'total' => $campaign->orderTotal($reservation->quantities),
        'store' => $store,
        'date' => $reservation->date,
        'timeSlot' => $reservation->timeSlot,
    ])

    <section class="panel">
        <h2 class="panel__title">お客様情報</h2>
        <dl class="summary-list">
            <dt>お名前</dt><dd>{{ $reservation->name }}</dd>
            <dt>ふりがな</dt><dd>{{ $reservation->kana }}</dd>
            <dt>電話番号</dt><dd>{{ $reservation->tel }}</dd>
            <dt>ご要望</dt><dd class="pre-wrap">{{ $reservation->note !== '' ? $reservation->note : '（ご要望なし）' }}</dd>
        </dl>
    </section>

    <p class="notice">お支払いは、お受け取りの際に店舗でお願いいたします。</p>

    <div class="actions">
        <form method="post" action="{{ route('seasonal.reservations.store', $campaign->slug) }}" data-submit-once>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button type="submit" class="button button--primary">予約する</button>
        </form>
        <form method="post" action="{{ route('seasonal.reservations.edit', $campaign->slug) }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button type="submit" class="button">修正する</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/submit-once.js') }}?v={{ filemtime(public_path('js/submit-once.js')) }}" defer></script>
@endpush
