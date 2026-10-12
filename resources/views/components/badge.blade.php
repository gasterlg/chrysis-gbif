@props(['color' => 'slate'])
@php
$colors = [
    'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
    'green' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    'red' => 'bg-red-50 text-red-800 ring-red-200',
    'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
    'blue' => 'bg-sky-50 text-sky-800 ring-sky-200',
];
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', $colors[$color] ?? $colors['slate']]) }}>{{ $slot }}</span>
