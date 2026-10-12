<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    @livewireStyles
</head>
<body class="flex h-full items-center justify-center text-slate-800 antialiased">
    {{ $slot }}
    <x-toasts />
    @livewireScripts
</body>
</html>
