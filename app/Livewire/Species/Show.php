<?php

namespace App\Livewire\Species;

use App\Livewire\Concerns\WithDataTable;
use App\Models\GbifMatch;
use App\Models\Occurrence;
use App\Models\Species;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use Livewire\Component;

class Show extends Component
{
    use WithDataTable;

    public int $idsp;

    public function mount(int $idsp): void
    {
        abort_unless(Species::query()->whereKey($idsp)->exists(), 404);
        $this->idsp = $idsp;
        if ($this->sortField === '') {
            $this->sortField = 'year';
            $this->sortDirection = 'desc';
        }
    }

    protected function taxonKey(): int
    {
        return (int) (GbifMatch::query()->whereKey($this->idsp)->value('gbif_id') ?? 0);
    }

    protected function baseQuery(): Builder
    {
        // Legacy occurrences are linked by gbif_taxon_key = match.gbif_id (no gbifID column exists).
        return Occurrence::query()->where('gbif_taxon_key', $this->taxonKey() ?: -1)->toBase();
    }

    protected function columns(): array
    {
        return [
            Column::make('id', 'ID')->sortable()->class('tabular-nums text-slate-500'),
            Column::make('scientific_name', 'Scientific name')->sortable()->searchable(),
            Column::make('country', 'Country')->sortable()->searchable(),
            Column::make('locality', 'Locality')->searchable('locality,state_province')
                ->format(fn ($r) => trim(implode(', ', array_filter([$r->locality, $r->state_province])))),
            Column::make('year', 'Year')->sortable('year,month')->class('tabular-nums')
                ->format(fn ($r) => $r->year ? ($r->month ? sprintf('%d-%02d', $r->year, $r->month) : $r->year) : '—'),
            Column::make('basis_of_record', 'Basis')->sortable(),
            Column::make('institution_code', 'Institution')->sortable()->searchable('institution_code,collection_code,dataset_name'),
            Column::make('recorded_by', 'Recorded by')->searchable(),
            Column::make('coords', 'Coordinates')->format(fn ($r) => $r->latitude !== null ? $r->latitude.', '.$r->longitude : '—'),
        ];
    }

    protected function filters(): array
    {
        $countries = Occurrence::query()->where('gbif_taxon_key', $this->taxonKey() ?: -1)
            ->whereNotNull('country')->distinct()->orderBy('country')->pluck('country');

        return [
            Filter::select('country', 'Country', $countries->combine($countries)->all()),
            Filter::select('basis', 'Basis of record', [
                'PRESERVED_SPECIMEN' => 'Preserved specimen', 'HUMAN_OBSERVATION' => 'Human observation',
                'MATERIAL_SAMPLE' => 'Material sample', 'OCCURRENCE' => 'Occurrence', 'MACHINE_OBSERVATION' => 'Machine observation',
            ], 'basis_of_record'),
        ];
    }

    protected function csvFilename(): string
    {
        return 'chrysis-occurrences-'.$this->idsp.'-'.now()->format('Ymd-His').'.csv';
    }

    protected function csvCitation(): ?string
    {
        return "Occurrence records originally harvested from GBIF.org by the legacy Chrawler.\n"
            ."GBIF.org (".now()->format('d F Y').") GBIF Occurrence Search https://www.gbif.org/occurrence/search?taxon_key={$this->taxonKey()}\n"
            ."Note: the legacy table stores no dataset keys, so per-dataset citations are not available for these rows.\n";
    }

    public function render()
    {
        $species = Species::query()->findOrFail($this->idsp);
        $match = GbifMatch::query()->find($this->idsp);

        return view('livewire.species.show', [
            'species' => $species,
            'match' => $match,
            ...$this->tableData(),
        ])->title($species->name);
    }
}
