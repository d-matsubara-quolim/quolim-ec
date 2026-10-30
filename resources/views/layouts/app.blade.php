<!DOCTYPE html>
<html lang="ja" @class(['has-header' => config('app.show_header')])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no">
    <meta name="description" content="@yield('description', 'ささいずみのWeb予約サイトです。')">
    <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>
    <link rel="icon" href="https://cdn.quolim.co.jp/img/sasaizumi/favicon/favicon.ico">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body>
    @if (config('app.show_header'))
        <x-site-header />
    @endif

    <main class="container">
        @yield('content')
    </main>

    <x-site-footer />

    @stack('scripts')
</body>
</html>
