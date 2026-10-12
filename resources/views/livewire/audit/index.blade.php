<div>
    <x-page-header title="Login audit" subtitle="Successful and failed logins, and logouts (gbif_audit_log). Admin only."
                   :breadcrumbs="['Dashboard' => route('home'), 'Login audit' => null]" />
    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected" />
</div>
