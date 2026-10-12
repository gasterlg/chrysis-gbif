@props(['items' => []])
{{-- items: [label => url|null] --}}
<nav aria-label="Breadcrumb" class="mb-2 text-sm text-slate-500">
    <ol class="flex flex-wrap items-center gap-1">
        @foreach ($items as $label => $url)
            <li class="flex items-center gap-1">
                @if (! $loop->first)<span aria-hidden="true">/</span>@endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" wire:navigate class="hover:text-emerald-700 hover:underline">{{ $label }}</a>
                @else
                    <span @if($loop->last) aria-current="page" class="text-slate-700" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
