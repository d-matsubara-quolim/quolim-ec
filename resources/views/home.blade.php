@extends('layouts.app')

@section('content')
    <h1 class="page-title">Web予約</h1>

    @forelse ($campaigns as $campaign)
        @if ($loop->first)
            <div class="campaign-grid">
        @endif
        <a href="{{ route('seasonal.products.index', $campaign->slug) }}" class="campaign-card">
            @if ($campaign->coverImage !== '')
                <img src="{{ $campaign->coverImage }}" alt="" class="campaign-card__image" loading="lazy">
            @endif
            <span class="campaign-card__body">
                <span class="campaign-card__period">
                    受付期間 {{ $campaign->opensAt->format('n/j') }} — {{ $campaign->closesAt->format('n/j H:i') }}
                </span>
                <span class="campaign-card__title">{{ $campaign->title }}</span>
                <span class="campaign-card__link">予約する</span>
            </span>
        </a>
        @if ($loop->last)
            </div>
        @endif
    @empty
        <p class="notice">現在受付中のWeb予約はありません。</p>
    @endforelse
@endsection
