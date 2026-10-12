<?php

namespace App\Livewire\Species;

use App\Gbif\OccurrenceImporter;
use App\Livewire\Concerns\WithDataTable;
use App\Models\GbifImportRun;
use App\Models\GbifMatch;
use App\Models\GbifOccurrence;
use App\Models\Occurrence;
use App\Models\Species;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use Livewire\Component;

class Show extends Component
{
    use WithDataTable;

    public const MAP_POINT_LIMIT = 5000;

    public int $idsp;

    public function mount(int $idsp): void
    {
        abort_unless(Species::query()->whereKey($idsp)->exists(), 404);
        $this->idsp = $idsp;
        if ($this->sortField === '') {
            $this->sortField = 'event_date';
            $this->sortDirection = 'desc';
        }
    }

    protected function taxonKey(): int
    {
        return (int) (GbifMatch::query()->whereKey($this->idsp)->value('gbif_id') ?? 0);
    }

    protected function occurrences()
    {
        return GbifOccurrence::query()->forTaxon($this->taxonKey() ?: -1);
    }

    protected function baseQuery(): Builder
    {
        return $this->occurrences()->toBase();
    }

    protected function rowKey(): string
    {
        return 'gbif_id';
    }

    protected function columns(): array
    {
        return [
            Column::make('gbif_id', 'gbifID')->sortable()->class('tabular-nums')
                ->format(fn ($r) => '<a class="text-sky-700 hover:underline" target="_blank" rel="noopener" href="https://www.gbif.org/occurrence/'.(int) $r->gbif_id.'">'.(int) $r->gbif_id.'</a>', html: true),
            Column::make('scientific_name', 'Scientific name')->sortable()->searchable(),
            Column::make('country', 'Country')->sortable()->searchable('country,country_code'),
            Column::make('locality', 'Locality')->searchable('locality,state_province')
                ->format(fn ($r) => \Illuminate\Support\Str::limit(trim(implode(', ', array_filter([$r->locality, $r->state_province]))), 80)),
            Column::make('coordinates', 'Coordinates')->sortable('latitude,longitude')->class('whitespace-nowrap tabular-nums')
                ->format(fn ($r) => $r->latitude !== null
                    ? sprintf('%.5f, %.5f', $r->latitude, $r->longitude).($r->coord_uncertainty_m ? ' ±'.number_format($r->coord_uncertainty_m).' m' : '')
                    : '—'),
            Column::make('event_date', 'Date')->sortable('year,month,event_date')->class('whitespace-nowrap tabular-nums')
                ->format(fn ($r) => $r->event_date ? substr($r->event_date, 0, 10) : ($r->year ?? '—')),
            Column::make('basis_of_record', 'Basis')->sortable(),
            Column::make('institution_code', 'Institution / catalogue')->sortable()->searchable('institution_code,collection_code,catalog_number,dataset_name')
                ->format(fn ($r) => trim(implode(' · ', array_filter([$r->institution_code, $r->catalog_number])))),
            Column::make('recorded_by', 'Recorded by')->searchable()->format(fn ($r) => \Illuminate\Support\Str::limit((string) $r->recorded_by, 40)),
            Column::make('license', 'Licence')->format(fn ($r) => str_replace(['http://creativecommons.org/licenses/', 'https://creativecommons.org/licenses/', '/legalcode'], '', (string) $r->license)),
            Column::make('media_count', 'Media')->sortable()->class('tabular-nums'),
        ];
    }

    protected function filters(): array
    {
        $countries = $this->occurrences()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country');

        return [
            Filter::select('country', 'Country', $countries->combine($countries)->all()),
            Filter::select('basis', 'Basis of record', [
                'PRESERVED_SPECIMEN' => 'Preserved specimen', 'HUMAN_OBSERVATION' => 'Human observation',
                'MATERIAL_SAMPLE' => 'Material sample', 'MATERIAL_CITATION' => 'Material citation', 'OCCURRENCE' => 'Occurrence', 'MACHINE_OBSERVATION' => 'Machine observation',
            ], 'basis_of_record'),
            Filter::boolean('coords', 'Has coordinates', fn ($q, $v) => $v ? $q->whereNotNull('latitude') : $q->whereNull('latitude')),
        ];
    }

    protected function csvFilename(): string
    {
        return 'chrysis-gbif-occurrences-'.$this->idsp.'-'.now()->format('Ymd-His').'.csv';
    }

    protected function csvCitation(): ?string
    {
        $keys = $this->occurrences()->whereNotNull('dataset_key')->distinct()->pluck('dataset_key');

        return 'GBIF.org ('.now()->format('d F Y').") GBIF Occurrence Search https://www.gbif.org/occurrence/search?taxon_key={$this->taxonKey()}\n\n"
            ."Datasets (cite each; see https://www.gbif.org/dataset/<key>):\n".$keys->map(fn ($k) => "- {$k}")->implode("\n")."\n";
    }

    public function startImport(OccurrenceImporter $importer): void
    {
        $this->authorize('update-gbif');

        if ($this->taxonKey() <= 0) {
            $this->dispatch('toast', type: 'error', message: 'This species has no GBIF key (match.gbif_id = 0); fix the match first.');

            return;
        }
        if ($this->latestRun()?->isActive()) {
            $this->dispatch('toast', type: 'warning', message: 'An import for this species is already running.');

            return;
        }

        $run = $importer->start([$this->idsp], null, auth()->id());
        \App\Models\AuditLog::record('gbif_import_started', auth()->id(), auth()->user()->username, "idsp:{$this->idsp}", ['run' => $run->id]);
        $this->dispatch('toast', type: 'success', message: "Import #{$run->id} queued.");
    }

    public function latestRun(): ?GbifImportRun
    {
        // UI runs (scope = this species only) and CLI runs that imported records for it.
        $ui = GbifImportRun::query()->where(fn ($q) => $q
            ->where('scope', 'like', '%"idsp":['.$this->idsp.']%')
            ->orWhere('scope', 'like', '%"idsp":['.$this->idsp.',%')
            ->orWhere('scope', 'like', '%,'.$this->idsp.',%')
            ->orWhere('scope', 'like', '%,'.$this->idsp.']%'))->max('id');
        $cli = GbifOccurrence::query()->where('idsp', $this->idsp)->max('import_run_id');
        $id = max((int) $ui, (int) $cli);

        return $id ? GbifImportRun::find($id) : null;
    }

    public function render()
    {
        $species = Species::query()->findOrFail($this->idsp);
        $match = GbifMatch::query()->find($this->idsp);
        $points = $this->occurrences()->whereNotNull('latitude')
            ->limit(self::MAP_POINT_LIMIT)
            ->get(['gbif_id', 'latitude', 'longitude', 'country', 'locality', 'year'])
            ->map(fn ($o) => [(float) $o->latitude, (float) $o->longitude, (int) $o->gbif_id, trim(($o->locality ?? '').' '.($o->country ? "({$o->country})" : '')), $o->year])
            ->all();

        return view('livewire.species.show', [
            'species' => $species,
            'match' => $match,
            'points' => $points,
            'withCoords' => $this->occurrences()->whereNotNull('latitude')->count(),
            'legacyCount' => $match?->gbif_id ? Occurrence::query()->where('gbif_taxon_key', $match->gbif_id)->count() : 0,
            'run' => $this->latestRun(),
            ...$this->tableData(),
        ])->title($species->name);
    }
}
