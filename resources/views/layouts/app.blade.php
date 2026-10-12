<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    @livewireStyles
</head>
<body class="h-full text-slate-800 antialiased">
@auth
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-screen-2xl items-center gap-6 px-4 py-3">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 font-semibold text-emerald-800">
                <span class="inline-block h-3 w-3 rounded-full bg-gradient-to-br from-emerald-500 to-sky-600"></span>
                {{ config('app.name') }}
            </a>
            <nav class="flex flex-wrap gap-1 text-sm" aria-label="Main">
                @php
                    $nav = [
                        ['Dashboard', 'home', 'home', null],
                        ['Species', 'species.index', 'species.*', null],
                        ['Match review', 'review.matches', 'review.matches', 'update-gbif'],
                        ['Import review', 'review.occurrences', 'review.occurrences', 'update-gbif'],
                        ['Changes', 'review.changes', 'review.changes', 'update-gbif'],
                        ['History', 'history', 'history', 'update-gbif'],
                        ['Datasets', 'datasets', 'datasets', null],
                        ['Exports', 'exports', 'exports', null],
                        ['Users', 'users.index', 'users.*', 'admin'],
                        ['Login audit', 'audit.index', 'audit.*', 'admin'],
                    ];
                @endphp
                @foreach ($nav as [$label, $route, $pattern, $ability])
                    @if (! $ability || Gate::allows($ability))
                        <a href="{{ route($route) }}" wire:navigate
                           @class(['rounded-md px-3 py-1.5', 'bg-emerald-50 font-medium text-emerald-800' => request()->routeIs($pattern), 'text-slate-600 hover:bg-slate-100' => ! request()->routeIs($pattern)])
                           @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
            <div class="relative ml-auto" x-data="{ open: false }" x-on:click.outside="open = false">
                <button type="button" x-on:click="open = ! open" class="flex items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-slate-100" data-user-menu>
                    <span>{{ auth()->user()->displayName() }}</span>
                    <x-badge :color="auth()->user()->isAdmin() ? 'blue' : 'slate'">{{ auth()->user()->role }}</x-badge>
                    @can('update-gbif')<x-badge color="green">update_gbif</x-badge>@endcan
                </button>
                <div x-show="open" x-cloak class="absolute right-0 z-30 mt-1 w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                    <div class="px-3 py-2 text-xs text-slate-500">{{ auth()->user()->username }}</div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
@endauth

    <div class="mx-auto flex max-w-screen-2xl flex-col gap-6 px-4 py-6 lg:flex-row">
        <main class="min-w-0 flex-1">
            {{ $slot }}
        </main>
        <x-help-sidebar />
    </div>

    <x-toasts />
    <x-confirm-dialog />
    @livewireScripts
</body>
</html>
