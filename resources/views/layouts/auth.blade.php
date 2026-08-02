<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'JumpWash') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('offline.css') }}?v={{ filemtime(public_path('offline.css')) }}">
    @endif
</head>
<body class="auth-body">
    <div class="auth-shell">
        <div class="auth-glow auth-glow-left"></div>
        <div class="auth-glow auth-glow-right"></div>
        @yield('content')
    </div>
</body>
</html>
