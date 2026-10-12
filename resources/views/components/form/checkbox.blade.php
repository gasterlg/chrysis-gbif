@props(['name', 'label', 'help' => null, 'toggle' => false])
@php $id = 'f-'.str_replace(['.', '[', ']'], '-', $name); @endphp
<x-form.field :name="$name" :help="$help" :id="$id">
    <label for="{{ $id }}" class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
        @if ($toggle)
            <input id="{{ $id }}" type="checkbox" wire:model="{{ $name }}" class="peer sr-only" {{ $attributes }}>
            <span class="relative h-5 w-9 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
        @else
            <input id="{{ $id }}" type="checkbox" wire:model="{{ $name }}" class="rounded border-slate-300" {{ $attributes }}>
        @endif
        {{ $label }}
    </label>
</x-form.field>
