<div>
    <x-page-header :title="$species->name" :subtitle="trim(($species->author ?? '').' · '.($species->SUBF ?? ''), ' ·')"
                   :breadcrumbs="['Dashboard' => route('home'), 'Species' => route('species.index'), $species->name => null]">
        <x-slot:actions>
            @can('update-gbif')
                <x-confirm-button method="startImport" title="Import GBIF occurrences?" variant="primary"
                    message="Fetches every GBIF record for this species into gbif_occurrences (pending review). The legacy occurrences table is not touched."
                    confirm-label="Start import" data-import-button>Import from GBIF</x-confirm-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <x-card title="chrysis.net (local)">
            <dl class="grid grid-cols-[9rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                <dt class="text-slate-500">IDSP</dt><dd class="tabular-nums">{{ $species->IDSP }}</dd>
                <dt class="text-slate-500">Name</dt><dd><em>{{ $species->name }}</em> {{ $species->author }}</dd>
                <dt class="text-slate-500">Subfamily</dt><dd>{{ \App\Models\Species::clean($species->SUBF) ?? '—' }}</dd>
                <dt class="text-slate-500">Match row</dt>
                <dd>
                    @if (! $match)
                        <x-gbif-status status="none" />
                    @elseif (! $match->gbif_id)
                        <x-gbif-status status="failed" /> <span class="text-xs text-slate-500">the legacy lookup stored gbif_id = 0</span>
                    @else
                        <x-gbif-status status="matched" /> {{ $match->gbif_id }} · {{ $match->gbif_species }}
                    @endif
                </dd>
                <dt class="text-slate-500">GBIF records here</dt>
                <dd class="tabular-nums" data-imported>{{ number_format($rows->total()) }}{{ $search || array_filter($filters, fn ($v) => $v !== '') ? ' (filtered)' : '' }} · {{ number_format($withCoords) }} with coordinates</dd>
                <dt class="text-slate-500">Legacy table</dt><dd class="tabular-nums">{{ number_format($legacyCount) }} rows (no coordinates)</dd>
                <dt class="text-slate-500">Last import</dt>
                <dd @if ($run?->isActive()) wire:poll.2s @endif data-run-status="{{ $run?->status }}">
                    @if ($run)
                        #{{ $run->id }} <x-badge :color="match($run->status) { 'finished' => 'green', 'failed' => 'red', default => 'amber' }">{{ $run->status }}</x-badge>
                        <span class="text-xs text-slate-500">{{ number_format($run->fetched) }} fetched · {{ $run->inserted }} new · {{ $run->updated }} changed{{ $run->finished_at ? ' · '.$run->finished_at->diffForHumans() : '' }}</span>
                    @else
                        <span class="text-slate-400">never</span>
                    @endif
                </dd>
            </dl>
        </x-card>

        <livewire:species.gbif-panel :name="$species->name" :author="$species->author" :local-key="$match?->gbif_id ?: null" :key="'gbif-'.$species->IDSP" />
    </div>

    <h2 class="mb-2 text-lg font-semibold">Map <span class="text-sm font-normal text-slate-500">({{ number_format(count($points)) }} georeferenced records{{ $withCoords > count($points) ? ', first '.number_format(count($points)).' shown' : '' }})</span></h2>
    @if ($points)
        <x-occurrence-map :points="$points" class="mb-6" wire:key="map-{{ $species->IDSP }}-{{ $withCoords }}" />
    @else
        <x-card class="mb-6"><p class="text-sm text-slate-500">No georeferenced GBIF records imported yet.@can('update-gbif') Use “Import from GBIF”.@endcan</p></x-card>
    @endif

    <h2 class="mb-2 text-lg font-semibold">GBIF occurrences <span class="text-sm font-normal text-slate-500">(gbif_occurrences, pending review)</span></h2>
    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected"
             search-placeholder="Search locality, country, collector, catalogue…" />
</div>
