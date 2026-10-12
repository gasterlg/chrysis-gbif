@props(['title' => null])
<section {{ $attributes->class('rounded-lg border border-slate-200 bg-white p-4 shadow-sm') }}>
    @if ($title)<h2 class="mb-3 text-base font-semibold text-slate-800">{{ $title }}</h2>@endif
    {{ $slot }}
</section>
