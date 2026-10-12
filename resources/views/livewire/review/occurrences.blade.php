<div>
    <x-page-header title="Import review" subtitle="Imported GBIF records waiting for approval. Flags: far from the species' usual range, or a country with very few records."
                   :breadcrumbs="['Dashboard' => route('home'), 'Review' => null, 'Occurrences' => null]">
        <x-slot:actions>
            @if ($idsp)
                <a href="{{ route('species.show', $idsp) }}" wire:navigate class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Species page</a>
                <x-confirm-button method="approveUnflagged" variant="primary" title="Approve unflagged records?"
                    :message="(($summary->pending ?? 0) - ($summary->flagged ?? 0)).' pending records of this species have no flags and will be approved.'"
                    confirm-label="Approve">Approve all unflagged</x-confirm-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2 text-sm" data-review-summary>
        <x-badge color="amber">pending: {{ number_format($summary->pending ?? 0) }}</x-badge>
        <x-badge color="red">flagged: {{ number_format($summary->flagged ?? 0) }}</x-badge>
        <x-badge color="blue">changed: {{ number_format($summary->changed ?? 0) }}</x-badge>
        <x-badge color="green">approved: {{ number_format($summary->approved ?? 0) }}</x-badge>
        <x-badge color="slate">rejected: {{ number_format($summary->rejected ?? 0) }}</x-badge>
        @unless ($idsp)<span class="text-slate-500">Pick a species to see its map and approve in bulk.</span>@endunless
    </div>

    @if ($points)
        <div wire:key="review-map-{{ $idsp }}-{{ count($points) }}" wire:ignore class="mb-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
             x-data="{ init() {
                const pts = @js($points);
                const map = L.map(this.$refs.map).setView([45, 12], 4);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
                const g = L.featureGroup(pts.map(p => L.circleMarker([p[0], p[1]], { radius: p[5] ? 6 : 4, weight: 1, color: p[5] ? '#991b1b' : '#065f46', fillColor: p[5] ? '#ef4444' : '#10b981', fillOpacity: .75 })
                    .bindPopup(`<a href='https://www.gbif.org/occurrence/${p[2]}' target='_blank' rel='noopener'>${p[2]}</a><br>${(p[3] || '').replace(/[<>&]/g, '')} ${p[4] ?? ''}`))).addTo(map);
                map.fitBounds(g.getBounds().pad(0.1), { maxZoom: 9 }); setTimeout(() => map.invalidateSize(), 100);
             } }">
            <div x-ref="map" style="height: 360px"></div>
            <div class="border-t border-slate-200 px-3 py-1.5 text-xs text-slate-500"><span class="text-red-600">●</span> flagged · <span class="text-emerald-600">●</span> not flagged (pending records only)</div>
        </div>
    @endif

    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected" search-placeholder="Search name, country, locality…" />
</div>
