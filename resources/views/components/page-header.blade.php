@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div class="mb-6">
    @if ($breadcrumbs)
        <x-breadcrumbs :items="$breadcrumbs" />
    @endif
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
        </div>
        @isset($actions)<div class="flex gap-2">{{ $actions }}</div>@endisset
    </div>
</div>
