<div>
    <x-page-header title="Dashboard" subtitle="Read-only view of chrysis_1 and the legacy Chrawler match table." />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card><div class="text-sm text-slate-500">Species in chrysis_1</div><div class="mt-1 text-3xl font-semibold" data-stat="species">{{ $species }}</div></x-card>
        <x-card><div class="text-sm text-slate-500">Matched to GBIF</div><div class="mt-1 text-3xl font-semibold text-emerald-700">{{ $matched }}</div></x-card>
        <x-card><div class="text-sm text-slate-500">Failed match (gbif_id = 0)</div><div class="mt-1 text-3xl font-semibold text-red-700">{{ $failed }}</div>
            <a href="{{ route('species.index', ['filters' => ['status' => 'failed']]) }}" wire:navigate class="text-sm text-emerald-800 hover:underline">Review list →</a></x-card>
        <x-card><div class="text-sm text-slate-500">Local occurrences</div><div class="mt-1 text-3xl font-semibold">{{ number_format($occurrences) }}</div></x-card>
    </div>
</div>
