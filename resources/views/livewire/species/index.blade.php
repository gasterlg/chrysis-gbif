<div>
    <x-page-header title="Species" subtitle="All species in chrysis_1._specie with their legacy GBIF match. Read-only."
                   :breadcrumbs="['Dashboard' => route('home'), 'Species' => null]" />
    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected"
             search-placeholder="Search genus, epithet, author, GBIF name…"
             :row-url="fn ($r) => route('species.show', $r->IDSP)"
             :row-class="fn ($r) => $r->gbif_status === 'failed' ? 'bg-red-50/50' : ''" />
</div>
