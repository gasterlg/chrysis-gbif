@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])
{{-- Open: $dispatch('open-modal', '{{ name }}') or $this->dispatch('open-modal', name: ...) ; close: 'close-modal' --}}
<div x-data="{ show: false, name: @js($name) }"
     x-on:open-modal.window="show = ($event.detail === name || $event.detail?.name === name) ? true : show"
     x-on:close-modal.window="if ($event.detail === name || $event.detail?.name === name || ! $event.detail) show = false"
     x-on:keydown.escape.window="show = false"
     x-show="show" x-cloak class="fixed inset-0 z-40 flex items-center justify-center p-4" role="dialog" aria-modal="true" data-modal="{{ $name }}">
    <div class="absolute inset-0 bg-slate-900/40" x-on:click="show = false"></div>
    <div class="relative w-full {{ $maxWidth }} rounded-lg bg-white p-5 shadow-xl" x-trap.noscroll="show">
        @if ($title)<h2 class="mb-3 text-lg font-semibold">{{ $title }}</h2>@endif
        {{ $slot }}
        @isset($footer)<div class="mt-5 flex justify-end gap-2">{{ $footer }}</div>@endisset
    </div>
</div>
