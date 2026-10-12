<div>
    <x-page-header :title="$species->name" :subtitle="trim(($species->author ?? '').' · '.($species->SUBF ?? ''), ' ·')"
                   :breadcrumbs="['Dashboard' => route('home'), 'Species' => route('species.index'), $species->name => null]" />

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <x-card title="chrysis.net (local)">
            <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1.5 text-sm">
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
                <dt class="text-slate-500">Local records</dt><dd class="tabular-nums">{{ number_format($rows->total()) }}{{ $search || array_filter($filters) ? ' (filtered)' : '' }}</dd>
            </dl>
        </x-card>

        <livewire:species.gbif-panel :name="$species->name" :author="$species->author" :local-key="$match?->gbif_id ?: null" :key="'gbif-'.$species->IDSP" />
    </div>

    <h2 class="mb-2 text-lg font-semibold">Local occurrences <span class="text-sm font-normal text-slate-500">(legacy table; coordinates are NULL for every row, see plan §0)</span></h2>
    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected"
             search-placeholder="Search locality, country, collector…" />
</div>
