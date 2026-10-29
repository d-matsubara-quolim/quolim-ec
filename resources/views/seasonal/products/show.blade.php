@extends('layouts.app')

@section('title', $product->name)

@php
    $storeNames = collect($campaign->stores)->filter(fn ($store) => $product->isAvailableAt($store->code))->map(fn ($store) => $store->name);
@endphp

@section('content')
    <p class="breadcrumb"><a href="{{ route('seasonal.products.index', $campaign->slug) }}">&lsaquo; {{ $campaign->title }}</a></p>

    <article class="product-detail">
        <img src="{{ $product->image }}" alt="{{ $product->name }}" class="product-detail__image">
        <div class="product-detail__body">
            <h1 class="page-title">{{ $product->name }}</h1>
            @if ($product->nameEn !== '')
                <p class="product-card__name-en">{{ $product->nameEn }}</p>
            @endif
            <p class="product-card__meta">商品ID：{{ $product->id }}</p>
            <p class="product-detail__price"><x-yen :amount="$product->price" /><span class="tax">（税込）</span> / {{ $product->unit }}</p>
            @if ($product->note !== '')
                <p class="product-card__note">{{ $product->note }}</p>
            @endif
            <p>受取店舗：{{ $storeNames->join('、') }}</p>

            @if ($product->description !== '')
                <div class="product-detail__description">{!! nl2br(e($product->description)) !!}</div>
            @endif
        </div>
    </article>

    <div class="actions">
        <a href="{{ route('seasonal.products.index', $campaign->slug) }}" class="button button--primary">予約フォームへ</a>
    </div>
@endsection
