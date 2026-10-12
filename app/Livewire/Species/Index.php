<?php

namespace App\Livewire\Species;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Species;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Species')]
class Index extends Component
{
    use WithDataTable;

    public const STATUSES = ['matched' => 'Matched', 'failed' => 'Failed (gbif_id = 0)', 'none' => 'No match row'];

    protected function baseQuery(): Builder
    {
        return Species::withMatchStatus()->toBase();
    }

    protected function rowKey(): string
    {
        return 'IDSP';
    }

    protected function rowKeyColumn(): string
    {
        return '_specie.IDSP';
    }

    protected function columns(): array
    {
        return [
            Column::make('name', 'Species')
                ->sortable('_specie.GEN,_specie.SP,_specie.SSP')
                ->searchable('_specie.GEN,_specie.SP,_specie.SSP')
                ->format(fn ($r) => trim(implode(' ', array_filter([$r->GEN, Species::clean($r->SP), Species::clean($r->SSP)])))),
            Column::make('AUTH', 'Author')->searchable('_specie.AUTH')->format(fn ($r) => Species::clean($r->AUTH))->class('text-slate-600'),
            Column::make('SUBF', 'Subfamily')->sortable('_specie.SUBF'),
            Column::make('IDSP', 'IDSP')->sortable('_specie.IDSP')->class('tabular-nums text-slate-500'),
            Column::make('gbif_id', 'GBIF key')->sortable('m.gbif_id')->class('tabular-nums')
                ->format(fn ($r) => $r->gbif_id ? '<a class="text-sky-700 hover:underline" target="_blank" rel="noopener" href="https://www.gbif.org/species/'.(int) $r->gbif_id.'">'.(int) $r->gbif_id.'</a>' : e($r->gbif_id ?? '—'), html: true),
            Column::make('gbif_species', 'GBIF name')->searchable('m.gbif_species'),
            Column::make('gbif_status', 'Status')->sortable('gbif_status')
                ->format(fn ($r) => view('components.gbif-status', ['status' => $r->gbif_status])->render(), html: true),
        ];
    }

    protected function filters(): array
    {
        $subfamilies = Species::query()->distinct()->orderBy('SUBF')->pluck('SUBF')->filter(fn ($s) => Species::clean($s))->values();

        return [
            Filter::select('status', 'GBIF status', self::STATUSES)
                ->using(fn ($q, $v) => match ($v) {
                    'matched' => $q->where('m.gbif_id', '>', 0),
                    'failed' => $q->whereNotNull('m.idsp')->where(fn ($q) => $q->whereNull('m.gbif_id')->orWhere('m.gbif_id', 0)),
                    'none' => $q->whereNull('m.idsp'),
                }),
            Filter::select('subfamily', 'Subfamily', $subfamilies->combine($subfamilies)->all(), '_specie.SUBF'),
        ];
    }

    protected function bulkActions(): array
    {
        return ['export-selected' => 'Export selected'];
    }

    public function bulkExportSelected(array $ids)
    {
        $this->dispatch('toast', type: 'success', message: count($ids).' species exported.');

        return $this->exportCsv(array_map('intval', $ids));
    }

    protected function csvFilename(): string
    {
        return 'chrysis-species-'.now()->format('Ymd-His').'.csv';
    }

    public function mount(): void
    {
        if ($this->sortField === '') {
            $this->sortField = 'name';
        }
    }

    public function render()
    {
        return view('livewire.species.index', $this->tableData());
    }
}
