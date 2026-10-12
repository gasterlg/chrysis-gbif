<section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" data-gbif-panel="ready">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-800">Live GBIF match</h2>
        <button type="button" wire:click="refresh" class="text-xs text-slate-500 underline hover:text-slate-800">Refresh</button>
    </div>
    @if ($error)
        <p class="text-sm text-red-700" role="alert">{{ $error }}</p>
    @elseif (! $result['usage_key'])
        <p class="text-sm text-amber-800">GBIF found no match for this name ({{ $result['match_type'] }}).</p>
    @else
        <dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1.5 text-sm">
            <dt class="text-slate-500">GBIF key</dt>
            <dd><a href="https://www.gbif.org/species/{{ $result['usage_key'] }}" target="_blank" rel="noopener" class="text-sky-700 hover:underline" data-gbif-key>{{ $result['usage_key'] }}</a></dd>
            <dt class="text-slate-500">Name</dt><dd>{{ $result['name'] }}</dd>
            <dt class="text-slate-500">Rank / status</dt><dd>{{ $result['rank'] }} · {{ $result['status'] }}</dd>
            @if ($result['accepted_key'] && $result['accepted_key'] !== $result['usage_key'])
                <dt class="text-slate-500">Accepted</dt><dd>{{ $result['accepted_name'] }} ({{ $result['accepted_key'] }})</dd>
            @endif
            <dt class="text-slate-500">Match</dt>
            <dd><x-badge :color="$result['match_type'] === 'EXACT' ? 'green' : 'amber'">{{ $result['match_type'] }}</x-badge> confidence {{ $result['confidence'] }}</dd>
            <dt class="text-slate-500">Family</dt><dd>{{ $result['family'] ?? '—' }}</dd>
            <dt class="text-slate-500">GBIF records</dt><dd class="tabular-nums">{{ $count !== null ? number_format($count) : '—' }}</dd>
            <dt class="text-slate-500">vs. local match</dt>
            <dd>
                @if (! $localKey)
                    <x-badge color="red">no local key</x-badge>
                @elseif ($agrees)
                    <x-badge color="green">agrees ({{ $localKey }})</x-badge>
                @else
                    <x-badge color="amber">differs: local {{ $localKey }}</x-badge>
                @endif
            </dd>
        </dl>
    @endif
</section>
