@props(['name', 'label' => null, 'help' => null, 'options' => [], 'placeholder' => null, 'live' => false])
@php $id = 'f-'.str_replace(['.', '[', ']'], '-', $name); @endphp
<x-form.field :name="$name" :label="$label" :help="$help" :id="$id">
    <select id="{{ $id }}" {{ $live ? 'wire:model.live' : 'wire:model' }}="{{ $name }}"
            {{ $attributes->class(['block w-full rounded-md border px-3 py-2 text-sm', 'border-red-400' => $errors->has($name), 'border-slate-300' => ! $errors->has($name)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
    </select>
</x-form.field>
