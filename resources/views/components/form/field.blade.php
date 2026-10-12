@props(['name', 'label' => null, 'help' => null, 'id' => null])
@php $id = $id ?? 'f-'.str_replace(['.', '[', ']'], '-', $name); @endphp
<div {{ $attributes->class('space-y-1') }}>
    @if ($label)<label for="{{ $id }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>@endif
    {{ $slot }}
    @if ($help)<p class="text-xs text-slate-500" id="{{ $id }}-help">{{ $help }}</p>@endif
    @error($name)<p class="text-xs text-red-600" id="{{ $id }}-error" role="alert">{{ $message }}</p>@enderror
</div>
