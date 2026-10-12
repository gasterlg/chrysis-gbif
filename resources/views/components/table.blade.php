@props([
    'rows',
    'columns',
    'tableFilters' => [],
    'bulkActions' => [],
    'rowKey' => 'id',
    'rowUrl' => null,       {{-- fn ($row) => url --}}
    'rowClass' => null,     {{-- fn ($row) => classes --}}
    'exportable' => true,
    'searchPlaceholder' => 'Search…',
    'sortField' => '',
    'sortDirection' => 'asc',
    'selected' => [],
])
@php
    $pageKeys = collect($rows->items())->map(fn ($r) => (string) data_get($r, $rowKey))->all();
    $allOnPage = $pageKeys !== [] && array_diff($pageKeys, $selected) === [];
    $selectable = $bulkActions !== [];
@endphp
<div class="rounded-lg border border-slate-200 bg-white shadow-sm" data-table>
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-end gap-3 border-b border-slate-200 p-3">
        <div class="min-w-56 flex-1">
            <label class="sr-only" for="table-search">Search</label>
            <input id="table-search" type="search" wire:model.live.debounce.350ms="search" placeholder="{{ $searchPlaceholder }}"
                   class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-emerald-600 focus:ring-emerald-600 focus:outline-none">
        </div>

        @foreach ($tableFilters as $filter)
            <div class="text-sm">
                <label class="block text-xs font-medium text-slate-500">{{ $filter->label }}</label>
                @if ($filter->type === 'select')
                    <select wire:model.live="filters.{{ $filter->key }}" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="">All</option>
                        @foreach ($filter->options as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @elseif ($filter->type === 'boolean')
                    <select wire:model.live="filters.{{ $filter->key }}" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="">Any</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                @elseif ($filter->type === 'date_range')
                    <div class="flex gap-1">
                        <input type="date" wire:model.live="filters.{{ $filter->key }}_from" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                        <input type="date" wire:model.live="filters.{{ $filter->key }}_to" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                    </div>
                @endif
            </div>
        @endforeach

        <button type="button" wire:click="clearFilters" class="text-sm text-slate-500 underline hover:text-slate-800">Reset</button>

        <div class="ml-auto flex items-center gap-2">
            @if ($selectable)
                <span class="text-sm text-slate-500" data-selected-count>{{ count($selected) }} selected</span>
                @foreach ($bulkActions as $action => $label)
                    <button type="button" wire:click="runBulkAction('{{ $action }}')"
                            class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-50"
                            @disabled(count($selected) === 0)>{{ $label }}</button>
                @endforeach
            @endif
            @if ($exportable)
                <button type="button" wire:click="exportCsv" class="rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-800">Export CSV</button>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    @if ($selectable)
                        <th class="w-8 px-3 py-2">
                            <input type="checkbox" aria-label="Select page" @checked($allOnPage)
                                   wire:click="togglePage(@js($pageKeys))" class="rounded border-slate-300">
                        </th>
                    @endif
                    @foreach ($columns as $column)
                        <th scope="col" class="px-3 py-2 text-left font-semibold text-slate-700 {{ $column->class }}">
                            @if ($column->sortable)
                                <button type="button" wire:click="sortBy('{{ $column->key }}')" class="inline-flex items-center gap-1 hover:text-emerald-700">
                                    {{ $column->label }}
                                    @if ($sortField === $column->key)
                                        <span aria-hidden="true">{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span aria-hidden="true" class="text-slate-300">↕</span>
                                    @endif
                                </button>
                            @else
                                {{ $column->label }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    @php $key = (string) data_get($row, $rowKey); @endphp
                    <tr wire:key="row-{{ $key }}" class="hover:bg-slate-50 {{ $rowClass ? $rowClass($row) : '' }}">
                        @if ($selectable)
                            <td class="px-3 py-2"><input type="checkbox" value="{{ $key }}" wire:model.live="selected" class="rounded border-slate-300"></td>
                        @endif
                        @foreach ($columns as $i => $column)
                            <td class="px-3 py-2 align-top {{ $column->class }}">
                                @php $value = $column->value($row); @endphp
                                @if ($i === 0 && $rowUrl)
                                    <a href="{{ $rowUrl($row) }}" wire:navigate class="font-medium text-emerald-800 hover:underline">{!! $column->html ? $value : e($value) !!}</a>
                                @else
                                    {!! $column->html ? $value : e($value) !!}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) + ($selectable ? 1 : 0) }}" class="px-3 py-8 text-center text-slate-500">No records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 p-3 text-sm">
        <div class="flex items-center gap-2 text-slate-600">
            <span>{{ number_format($rows->total()) }} records</span>
            <select wire:model.live="perPage" class="rounded-md border border-slate-300 px-2 py-1 text-sm" aria-label="Rows per page">
                @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}">{{ $n }} / page</option>@endforeach
            </select>
        </div>
        {{ $rows->links() }}
    </div>
</div>
