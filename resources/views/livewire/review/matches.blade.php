<div>
    <x-page-header title="Match review" subtitle="GBIF match proposals from gbif:match. Accepting writes one row of `match` (with a snapshot for rollback)."
                   :breadcrumbs="['Dashboard' => route('home'), 'Review' => null, 'Matches' => null]">
        <x-slot:actions>
            <a href="{{ route('review.changes') }}" wire:navigate class="rounded-md border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Applied changes</a>
            <x-confirm-button method="acceptAllExact" variant="primary" title="Accept all exact matches?"
                :message="$exactReady.' pending proposals are EXACT, ACCEPTED (not synonyms) with confidence ≥ 95. Each one updates its match row; every change can be rolled back.'"
                confirm-label="Accept {{ $exactReady }}" :disabled="$exactReady === 0">Accept all exact ≥95 ({{ $exactReady }})</x-confirm-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (['pending' => 'amber', 'same' => 'slate', 'accepted' => 'green', 'rejected' => 'red', 'rolled_back' => 'red'] as $s => $c)
            <x-badge :color="$c">{{ $s }}: {{ $counts[$s] ?? 0 }}</x-badge>
        @endforeach
    </div>

    <x-table :rows="$rows" :columns="$columns" :table-filters="$tableFilters" :bulk-actions="$bulkActions" :row-key="$rowKey"
             :sort-field="$sortField" :sort-direction="$sortDirection" :selected="$selected" search-placeholder="Search species or GBIF name…" />

    <x-modal name="review-proposal" title="Review match" max-width="max-w-2xl">
        @if ($reviewing)
            <p class="mb-3 text-sm"><em class="font-medium">{{ $reviewing->query_name }}</em> · current key {{ $reviewing->current_gbif_id ?: '—' }}</p>
            <div class="mb-4 rounded-md bg-slate-50 p-3 text-sm">
                <div>GBIF: <strong>{{ $reviewing->usage_name ?? 'no match' }}</strong> ({{ $reviewing->match_type }} {{ $reviewing->confidence }}, {{ $reviewing->rank }}, {{ $reviewing->taxon_status }})</div>
                @if ($reviewing->note)<div class="mt-1 text-xs text-slate-500">{{ $reviewing->note }}</div>@endif
            </div>
            <h3 class="mb-1 text-sm font-semibold">Alternatives</h3>
            <ul class="mb-4 divide-y divide-slate-100 rounded-md border border-slate-200 text-sm" data-alternatives>
                @forelse ($reviewing->alternatives ?? [] as $alt)
                    <li class="flex items-center justify-between gap-2 px-3 py-1.5">
                        <span>{{ $alt['name'] }} <span class="text-xs text-slate-500">{{ $alt['rank'] }} · {{ $alt['status'] }} · {{ $alt['match_type'] }} {{ $alt['confidence'] }} · {{ $alt['key'] }}</span></span>
                        <button type="button" wire:click="acceptAlternative({{ $reviewing->id }}, {{ $alt['key'] }})" class="rounded border border-emerald-600 px-2 py-0.5 text-xs text-emerald-800 hover:bg-emerald-50">Use this</button>
                    </li>
                @empty
                    <li class="px-3 py-2 text-slate-500">GBIF returned no alternatives.</li>
                @endforelse
            </ul>
            <form wire:submit="acceptManual" class="flex items-end gap-2">
                <x-form.input name="manualKey" label="Or enter a GBIF taxon key" help="Synonyms are resolved to their accepted taxon." class="w-48" inputmode="numeric" />
                <x-button type="submit" class="mb-5">Use key</x-button>
            </form>
        @endif
        <x-slot:footer><x-button variant="secondary" x-on:click="$dispatch('close-modal', { name: 'review-proposal' })">Close</x-button></x-slot:footer>
    </x-modal>
</div>
