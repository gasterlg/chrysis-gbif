<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    @livewireStyles
</head>
<body class="flex min-h-full items-center justify-center text-slate-800 antialiased">
    <div class="flex w-full max-w-3xl flex-col items-center gap-6 px-4 py-8 md:flex-row md:items-start md:justify-center">
        <div>{{ $slot }}</div>
        <div class="w-full md:w-72"><x-help-sidebar /></div>
    </div>
    <x-toasts />
    @livewireScripts
</body>
</html>
