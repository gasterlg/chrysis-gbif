<div>
    <x-page-header title="History" subtitle="Every login, match decision, rollback, approval, import, export and user change. Times are UTC."
                   :breadcrumbs="['Dashboard' => route('home'), 'History' => null]" />
    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected" search-placeholder="Search action, user, target, details…" />
</div>
