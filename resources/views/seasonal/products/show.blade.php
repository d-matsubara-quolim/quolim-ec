@extends('layouts.app')

@section('title', $product->name)

@php
    $storeNames = collect($campaign->stores)->filter(fn ($store) => $product->isAvailableAt($store->code))->map(fn ($store) => $store->name);
@endphp

@section('content')
    <p class="breadcrumb"><a href="{{ route('seasonal.products.index', $campaign->slug) }}">← {{ $campaign->title }}</a></p>

    <article class="product-detail">
        <div class="product-detail__media">
            <img src="{{ $product->image }}" alt="{{ $product->name }}" class="product-detail__image">
        </div>
        <div class="product-detail__body">
            @if ($product->note !== '')
                <span class="badge">{{ $product->note }}</span>
            @endif
            <h1 class="page-title">{{ $product->name }}</h1>
            @if ($product->nameEn !== '')
                <p class="product-card__name-en">{{ $product->nameEn }}</p>
            @endif
            <p class="product-detail__price"><x-yen :amount="$product->price" /><span class="tax">税込 / {{ $product->unit }}</span></p>

            @if ($product->description !== '')
                <div class="product-detail__description">{!! nl2br(e($product->description)) !!}</div>
            @endif

            <dl class="summary-list summary-list--compact">
                <dt>受取店舗</dt><dd>{{ $storeNames->join('、') }}</dd>
                <dt>商品ID</dt><dd>{{ $product->id }}</dd>
            </dl>

            <a href="{{ route('seasonal.products.index', $campaign->slug) }}" class="button button--primary button--block">予約フォームへ</a>
        </div>
    </article>
@endsection
