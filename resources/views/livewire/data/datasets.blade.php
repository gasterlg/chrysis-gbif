<div>
    <x-page-header title="Datasets" subtitle="GBIF datasets that hold the imported records (gbif_datasets), with licence and DOI for citation."
                   :breadcrumbs="['Dashboard' => route('home'), 'Datasets' => null]" />
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        @foreach (\App\Gbif\DatasetFetcher::HIGHLIGHT as $key => $label)
            @php $d = $highlights[$key] ?? null; $c = $hlCounts[$key] ?? null; @endphp
            <x-card data-highlight="{{ $key }}">
                <div class="mb-1"><x-badge color="blue">{{ $label }}</x-badge></div>
                <a href="https://www.gbif.org/dataset/{{ $key }}" target="_blank" rel="noopener" class="text-sm font-medium text-sky-800 hover:underline">{{ $d->title ?? $key }}</a>
                <dl class="mt-2 grid grid-cols-2 gap-1 text-sm">
                    <dt class="text-slate-500">Imported records</dt><dd class="tabular-nums">{{ number_format($c->n ?? 0) }}</dd>
                    <dt class="text-slate-500">Type specimens</dt><dd class="tabular-nums">{{ number_format($c->types ?? 0) }}</dd>
                    <dt class="text-slate-500">Images</dt><dd class="tabular-nums">{{ number_format($c->media ?? 0) }}</dd>
                    <dt class="text-slate-500">Licence</dt><dd class="truncate">{{ $d?->license ? str_replace(['http://creativecommons.org/licenses/', 'http://creativecommons.org/publicdomain/zero/1.0/legalcode', '/legalcode'], ['', 'CC0', ''], $d->license) : '—' }}</dd>
                </dl>
            </x-card>
        @endforeach
    </div>
    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected" search-placeholder="Search title or key…" />
</div>
