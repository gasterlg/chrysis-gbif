@props(['variant' => 'primary', 'type' => 'button'])
@php
$styles = [
    'primary' => 'bg-emerald-700 text-white hover:bg-emerald-800',
    'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
    'danger' => 'bg-red-600 text-white hover:bg-red-700',
];
@endphp
<button type="{{ $type }}" {{ $attributes->class(['inline-flex items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium disabled:opacity-50', $styles[$variant] ?? $styles['primary']]) }}>{{ $slot }}</button>
