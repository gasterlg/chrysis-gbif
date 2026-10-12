@props(['name', 'label' => null, 'help' => null, 'rows' => 4])
@php $id = 'f-'.str_replace(['.', '[', ']'], '-', $name); @endphp
<x-form.field :name="$name" :label="$label" :help="$help" :id="$id">
    <textarea id="{{ $id }}" rows="{{ $rows }}" wire:model="{{ $name }}"
              {{ $attributes->class(['block w-full rounded-md border px-3 py-2 text-sm', 'border-red-400' => $errors->has($name), 'border-slate-300' => ! $errors->has($name)]) }}></textarea>
</x-form.field>
