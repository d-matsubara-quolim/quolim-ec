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
    @if ($campaign->coverImage !== '')
        <img src="{{ $campaign->coverImage }}" alt="" class="cover-image">
    @endif

    <h1 class="page-title">{{ $campaign->title }}</h1>

    @includeIf("seasonal.campaigns.{$campaign->slug}.description")

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
            <h2 class="step__title"><span class="step__no">1</span>商品と個数を選ぶ</h2>

            <ul class="product-list">
                @foreach ($campaign->products as $product)
                    @php $onlyAt = count($product->storeCodes()) < count($campaign->stores) ? $product->storeCodes() : []; @endphp
                    <li class="product-card" data-product="{{ $product->id }}">
                        <a href="{{ route('seasonal.products.show', [$campaign->slug, $product->id]) }}" class="product-card__image-link">
                            <img src="{{ $product->image }}" alt="{{ $product->name }}" class="product-card__image" loading="lazy">
                        </a>
                        <div class="product-card__body">
                            <p class="product-card__name">
                                <a href="{{ route('seasonal.products.show', [$campaign->slug, $product->id]) }}">{{ $product->name }}</a>
                            </p>
                            @if ($product->nameEn !== '')
                                <p class="product-card__name-en">{{ $product->nameEn }}</p>
                            @endif
                            <p class="product-card__meta">商品ID：{{ $product->id }}</p>
                            <p class="product-card__price"><x-yen :amount="$product->price" /><span class="tax">（税込）</span></p>
                            @if ($product->note !== '')
                                <p class="product-card__note">{{ $product->note }}</p>
                            @endif
                            @if ($onlyAt !== [])
                                <p class="product-card__store-limit">
                                    {{ collect($onlyAt)->map(fn ($code) => $storeNames[$code])->join('・') }}でのお受け取りのみ
                                </p>
                            @endif
                        </div>
                        <div class="product-card__quantity">
                            <label for="quantity-{{ $product->id }}" class="visually-hidden">{{ $product->name }}の個数</label>
                            <select name="quantities[{{ $product->id }}]" id="quantity-{{ $product->id }}" class="quantity-select">
                                @for ($i = 0; $i <= $campaign->maxQuantityPerProduct; $i++)
                                    <option value="{{ $i }}" @selected((int) old("quantities.{$product->id}", 0) === $i)>{{ $i }}</option>
                                @endfor
                            </select>
                            <span>{{ $product->unit }}</span>
                            <p class="product-card__subtotal" data-subtotal><x-yen :amount="0" /></p>
                        </div>
                    </li>
                @endforeach
            </ul>

            <p class="total">合計 <x-yen :amount="0" id="total" /> <span class="tax">（税込）</span></p>
            @error('quantities')<p class="field-error">{{ $message }}</p>@enderror
            @error('quantities.*')<p class="field-error">{{ $message }}</p>@enderror
        </section>

        <section class="step">
            <h2 class="step__title"><span class="step__no">2</span>受取店舗を選ぶ</h2>
            <div class="field">
                <label for="store" class="field__label">受取店舗 <span class="required">必須</span></label>
                <select name="store" id="store" class="field__input" required>
                    <option value="">選択してください</option>
                    @foreach ($campaign->stores as $store)
                        <option value="{{ $store->code }}" @selected(old('store') === $store->code)>{{ $store->name }}</option>
                    @endforeach
                </select>
                @error('store')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="step">
            <h2 class="step__title"><span class="step__no">3</span>受取日時を選ぶ</h2>
            <div class="field">
                <label for="date" class="field__label">受取日 <span class="required">必須</span></label>
                <select name="date" id="date" class="field__input" required>
                    <option value="">選択してください</option>
                    @foreach ($allDates as $date)
                        <option value="{{ $date }}" @selected(old('date') === $date)>{{ PickupStore::formatDate($date) }}</option>
                    @endforeach
                </select>
                @error('date')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="time_slot" class="field__label">受取時間 <span class="required">必須</span></label>
                <select name="time_slot" id="time_slot" class="field__input" required>
                    <option value="">選択してください</option>
                    @foreach ($allTimeSlots as $timeSlot)
                        <option value="{{ $timeSlot }}" @selected(old('time_slot') === $timeSlot)>{{ PickupStore::formatTimeSlot($timeSlot) }}</option>
                    @endforeach
                </select>
                @error('time_slot')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="step">
            <h2 class="step__title"><span class="step__no">4</span>連絡先を入力する</h2>
            <div class="field">
                <label for="name" class="field__label">お名前 <span class="required">必須</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" class="field__input" maxlength="20" autocomplete="name" required>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="kana" class="field__label">ふりがな <span class="required">必須</span></label>
                <input type="text" name="kana" id="kana" value="{{ old('kana') }}" class="field__input" maxlength="20" required>
                @error('kana')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="tel" class="field__label">電話番号 <span class="required">必須</span></label>
                <input type="tel" name="tel" id="tel" value="{{ old('tel') }}" class="field__input" maxlength="13" autocomplete="tel" inputmode="tel" placeholder="例）090-1234-5678" required>
                @error('tel')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="step">
            <h2 class="step__title"><span class="step__no">5</span>ご要望を書く</h2>
            <div class="field">
                <label for="note" class="field__label">ご要望 <span class="optional">任意</span></label>
                <textarea name="note" id="note" class="field__input" rows="3" maxlength="150">{{ old('note') }}</textarea>
                @error('note')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <p class="privacy-notice">
            ご入力いただいた個人情報は、<a href="{{ config('app.privacy_policy_url') }}" target="_blank" rel="noopener">プライバシーポリシー</a>に基づき適切に取り扱います。
        </p>

        <div class="actions">
            <button type="submit" class="button button--primary">確認画面へ</button>
        </div>
    </form>

    <script type="application/json" id="campaign-data">@json($clientData)</script>
@endsection

@push('scripts')
    <script src="{{ asset('js/seasonal/reservation.js') }}?v={{ filemtime(public_path('js/seasonal/reservation.js')) }}" defer></script>
@endpush
