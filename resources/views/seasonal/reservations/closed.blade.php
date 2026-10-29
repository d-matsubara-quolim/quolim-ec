@extends('layouts.app')

@section('title', $campaign->title)

@section('content')
    <h1 class="page-title">{{ $campaign->title }}</h1>

    @if (now()->lt($campaign->opensAt))
        <p class="notice">Web予約の受付は {{ $campaign->opensAt->format('n月j日 H:i') }} からです。</p>
    @else
        <p class="notice">Web予約の受付は終了しました。たくさんのご予約をありがとうございました。</p>
    @endif

    <div class="actions">
        <a href="{{ route('home') }}" class="button">トップへ戻る</a>
    </div>
@endsection
