@extends('layouts.app')

@section('title', $campaign->title)
@section('description', $campaign->description)

@use('App\Seasonal\PickupStore')

@php
    $storeNames = collect($campaign->stores)->mapWithKeys(fn ($store) => [$store->code => $store->name]);
    $allDates = collect($campaign->stores)->flatMap(fn ($store) => $store->dates)->unique()->sort()->values();
    $allTimeSlots = collect($campaign->stores)->flatMap(fn ($store) => $store->timeSlots)->unique()->sort()->values();
    $clientData = [
        'stores' => collect($campaign->stores)->mapWithKeys(fn ($store) => [$store->code => [
            'dates' => $store->dates,
            'timeSlots' => $store->timeSlots,
        ]]),
        'products' => collect($campaign->products)->mapWithKeys(fn ($product) => [$product->id => [
            'price' => $product->price,
            'stores' => $product->storeCodes(),
        ]]),
    ];
@endphp

@section('content')
    <section class="hero">
        @if ($campaign->coverImage !== '')
            <img src="{{ $campaign->coverImage }}" alt="" class="hero__image">
        @endif
        <div class="hero__body">
            <p class="hero__period">
                受付期間 {{ $campaign->opensAt->format('n/j') }} — {{ $campaign->closesAt->format('n/j H:i') }}
            </p>
            <h1 class="page-title">{{ $campaign->title }}</h1>
            @includeIf("seasonal.campaigns.{$campaign->slug}.description")
        </div>
    </section>

    @if (session('error'))
        <p class="alert" role="alert">{{ session('error') }}</p>
    @endif

    @if ($errors->any())
        <div class="alert" role="alert">
            <p>入力内容をご確認ください。</p>
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('seasonal.reservations.confirm', $campaign->slug) }}" id="reservation-form" novalidate>
        @csrf

        <section class="step">
            <h2 class="step__title"><span class="step__no">01</span>商品を選ぶ</h2>

            <ul class="product-grid">
                @foreach ($campaign->products as $product)
                    @php $onlyAt = count($product->storeCodes()) < count($campaign->stores) ? $product->storeCodes() : []; @endphp
                    <li class="product-card" data-product="{{ $product->id }}">
                        <a href="{{ route('seasonal.products.show', [$campaign->slug, $product->id]) }}" class="product-card__media">
                            <img src="{{ $product->image }}" alt="{{ $product->name }}" class="product-card__image" loading="lazy">
                        </a>
                        <div class="product-card__body">
                            @if ($product->note !== '')
                                <div class="product-card__badges">
                                    <span class="badge">{{ $product->note }}</span>
                                </div>
                            @endif
                            <p class="product-card__name">
                                <a href="{{ route('seasonal.products.show', [$campaign->slug, $product->id]) }}">{{ $product->name }}</a>
                            </p>
                            @if ($product->nameEn !== '')
                                <p class="product-card__name-en">{{ $product->nameEn }}</p>
                            @endif
                            <p class="product-card__price"><x-yen :amount="$product->price" /><span class="tax">税込</span></p>
                            @if ($onlyAt !== [])
                                <p class="product-card__store"><span class="badge badge--store"><span class="nowrap">{{ collect($onlyAt)->map(fn ($code) => $storeNames[$code])->join('・') }}</span> <span class="nowrap">のみ</span></span></p>
                            @endif
                        </div>
                        <div class="stepper">
                            <button type="button" class="stepper__button" data-step="-1" aria-label="{{ $product->name }}を1{{ $product->unit }}減らす">−</button>
                            <label for="quantity-{{ $product->id }}" class="visually-hidden">{{ $product->name }}の個数</label>
                            <input type="number" name="quantities[{{ $product->id }}]" id="quantity-{{ $product->id }}" value="{{ (int) old("quantities.{$product->id}", 0) }}" min="0" max="{{ $campaign->maxQuantityPerProduct }}" inputmode="numeric" class="stepper__input">
                            <span class="stepper__unit">{{ $product->unit }}</span>
                            <button type="button" class="stepper__button" data-step="1" aria-label="{{ $product->name }}を1{{ $product->unit }}増やす">＋</button>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="step__hint">各商品 {{ $campaign->maxQuantityPerProduct }} 個までご予約いただけます。</p>
            @error('quantities')<p class="field-error">{{ $message }}</p>@enderror
            @error('quantities.*')<p class="field-error">{{ $message }}</p>@enderror
        </section>

        <section class="step">
            <h2 class="step__title"><span class="step__no">02</span>受取店舗を選ぶ</h2>
            <fieldset class="choice-group">
                <legend class="visually-hidden">受取店舗</legend>
                <div class="choice-list choice-list--stores">
                    @foreach ($campaign->stores as $store)
                        <label class="choice choice--card">
                            <input type="radio" name="store" value="{{ $store->code }}" @checked(old('store') === $store->code) required>
                            <span class="choice__title">{{ $store->name }}</span>
                            <span class="choice__meta">
                                {{ collect($store->dates)->map(fn ($date) => PickupStore::formatDate($date))->join('・') }}<br>
                                {{ $store->pickupHours() }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('store')<p class="field-error">{{ $message }}</p>@enderror
        </section>

        <section class="step">
            <h2 class="step__title"><span class="step__no">03</span>受取日時を選ぶ</h2>
            <fieldset class="choice-group">
                <legend class="field__label">受取日</legend>
                <div class="choice-list">
                    @foreach ($allDates as $date)
                        <label class="choice">
                            <input type="radio" name="date" value="{{ $date }}" @checked(old('date') === $date) required>
                            <span class="choice__title">{{ PickupStore::formatDate($date) }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('date')<p class="field-error">{{ $message }}</p>@enderror

            <fieldset class="choice-group">
                <legend class="field__label">受取時間</legend>
                <div class="choice-list choice-list--times">
                    @foreach ($allTimeSlots as $timeSlot)
                        <label class="choice">
                            <input type="radio" name="time_slot" value="{{ $timeSlot }}" @checked(old('time_slot') === $timeSlot) required>
                            <span class="choice__title">{{ PickupStore::formatTimeSlot($timeSlot) }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @error('time_slot')<p class="field-error">{{ $message }}</p>@enderror
        </section>

        <section class="step">
            <h2 class="step__title"><span class="step__no">04</span>お客様情報</h2>
            <div class="field-grid">
                <div class="field">
                    <label for="name" class="field__label">お名前 <span class="required">必須</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" class="field__input" maxlength="20" autocomplete="name" placeholder="例）山田 花子" required>
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="kana" class="field__label">ふりがな <span class="required">必須</span></label>
                    <input type="text" name="kana" id="kana" value="{{ old('kana') }}" class="field__input" maxlength="20" placeholder="例）やまだ はなこ" required>
                    @error('kana')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="tel" class="field__label">電話番号 <span class="required">必須</span></label>
                    <input type="tel" name="tel" id="tel" value="{{ old('tel') }}" class="field__input" maxlength="13" autocomplete="tel" inputmode="tel" placeholder="例）090-1234-5678" required>
                    @error('tel')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="field field--wide">
                    <label for="note" class="field__label">ご要望 <span class="optional">任意</span></label>
                    <textarea name="note" id="note" class="field__input" rows="3" maxlength="150" placeholder="150文字以内">{{ old('note') }}</textarea>
                    @error('note')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <p class="privacy-notice">
            ご入力いただいた個人情報は、<a href="{{ config('app.privacy_policy_url') }}" target="_blank" rel="noopener">プライバシーポリシー</a>に基づき適切に取り扱います。
        </p>

        <div class="order-bar">
            <div class="order-bar__inner">
                <p class="order-bar__total">
                    <span class="order-bar__label">合計<span class="tax">税込</span></span>
                    <x-yen :amount="0" id="total" class="order-bar__amount" />
                    <span class="order-bar__count" id="item-count"></span>
                </p>
                <button type="submit" class="button button--primary">確認画面へ</button>
            </div>
        </div>
    </form>

    <script type="application/json" id="campaign-data">@json($clientData)</script>
@endsection

@push('scripts')
    <script src="{{ asset('js/seasonal/reservation.js') }}?v={{ filemtime(public_path('js/seasonal/reservation.js')) }}" defer></script>
@endpush
