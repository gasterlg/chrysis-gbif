@props(['method', 'args' => [], 'title' => 'Are you sure?', 'message' => '', 'confirmLabel' => 'Confirm', 'danger' => false, 'variant' => null])
@php
    $payload = \Illuminate\Support\Js::from([
        'title' => $title, 'message' => $message, 'confirmLabel' => $confirmLabel,
        'danger' => (bool) $danger, 'method' => $method, 'args' => array_values($args),
    ]);
@endphp
<x-button :variant="$variant ?? ($danger ? 'danger' : 'secondary')"
    x-on:click="$dispatch('confirm', Object.assign({{ $payload }}, { componentId: $wire.$id }))"
    {{ $attributes }}>{{ $slot }}</x-button>
