<div>
    <x-page-header title="Applied changes" subtitle="Every write to the legacy match table, with before/after snapshots. Roll back restores the before-image."
                   :breadcrumbs="['Dashboard' => route('home'), 'Review' => route('review.matches'), 'Changes' => null]" />
    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected" search-placeholder="Search IDSP, reason, user…" />
</div>
