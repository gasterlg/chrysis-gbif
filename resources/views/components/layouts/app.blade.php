<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @if (file_exists(public_path('css/app.css')))
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @endif
    @livewireStyles
</head>
<body>
    <header><strong>{{ config('app.name') }}</strong></header>
    <main>{{ $slot }}</main>
    @livewireScripts
</body>
</html>
