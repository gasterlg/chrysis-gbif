<div>
    <x-page-header title="Exports" subtitle="Approved GBIF records only. Each export is a zip with occurrences.csv, datasets.csv and CITATION.txt."
                   :breadcrumbs="['Dashboard' => route('home'), 'Exports' => null]" />
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Approved occurrences (CSV)">
            <p class="mb-3 text-sm text-slate-600">{{ number_format($approvedTotal) }} approved records · {{ number_format($pendingTotal) }} still pending review.</p>
            <form wire:submit="export" class="flex items-end gap-2">
                <div class="flex-1">
                    <label for="exp-species" class="block text-sm font-medium text-slate-700">Species</label>
                    <select id="exp-species" wire:model="species" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">All species ({{ number_format($approvedTotal) }})</option>
                        @foreach ($options as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <x-button type="submit" data-export>Download zip</x-button>
            </form>
        </x-card>

        <x-card title="GBIF Download API (citable DOI)">
            @if (! $downloadsEnabled)
                <p class="text-sm text-amber-800" data-downloads-disabled>Not configured. Add a GBIF.org account to <code>.env</code>
                    (<code>GBIF_DOWNLOAD_USER</code>, <code>GBIF_DOWNLOAD_PASSWORD</code>, <code>GBIF_DOWNLOAD_EMAIL</code>) to request a full Chrysididae download with a DOI.</p>
            @else
                @can('update-gbif')
                    <x-confirm-button method="requestDownload" variant="primary" title="Request GBIF download?" message="GBIF prepares a SIMPLE_CSV of all Chrysididae records (taxon 7903) with a DOI. It can take minutes to hours." confirm-label="Request">Request Chrysididae download</x-confirm-button>
                @endcan
            @endif
            @if ($downloads->isNotEmpty())
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @foreach ($downloads as $d)
                        <li class="flex items-center justify-between py-1.5">
                            <span>#{{ $d->id }} {{ $d->download_key }} · {{ $d->download_status }} @if ($d->download_doi) · <a class="text-sky-700 hover:underline" href="https://doi.org/{{ $d->download_doi }}">{{ $d->download_doi }}</a>@endif</span>
                            <span class="flex gap-2">
                                @if ($d->download_link)<a class="text-sky-700 hover:underline" href="{{ $d->download_link }}">zip</a>@endif
                                <button type="button" wire:click="poll({{ $d->id }})" class="text-xs underline">refresh</button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
