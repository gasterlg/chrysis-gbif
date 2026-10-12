@props(['name', 'label' => null, 'help' => null, 'type' => 'text', 'live' => false])
@php $id = 'f-'.str_replace(['.', '[', ']'], '-', $name); $model = $live ? 'wire:model.live.blur' : 'wire:model'; @endphp
<x-form.field :name="$name" :label="$label" :help="$help" :id="$id">
    <input id="{{ $id }}" type="{{ $type }}" {{ $model }}="{{ $name }}"
           @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
           {{ $attributes->class(['block w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-1', 'border-red-400 focus:ring-red-500' => $errors->has($name), 'border-slate-300 focus:border-emerald-600 focus:ring-emerald-600' => ! $errors->has($name)]) }}>
</x-form.field>
