<?php

namespace App\Livewire\Data;

use App\Gbif\DatasetFetcher;
use App\Livewire\Concerns\WithDataTable;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Datasets')]
class Datasets extends Component
{
    use WithDataTable;

    public function mount(): void
    {
        if ($this->sortField === '') {
            $this->sortField = 'records';
            $this->sortDirection = 'desc';
        }
    }

    protected function rowKey(): string
    {
        return 'dataset_key';
    }

    protected function rowKeyColumn(): string
    {
        return 'd.dataset_key';
    }

    protected function baseQuery(): Builder
    {
        $counts = DB::connection('chrysis')->table('gbif_occurrences')->whereNotNull('dataset_key')
            ->selectRaw("dataset_key, COUNT(*) records, SUM(CASE WHEN state = 'approved' THEN 1 ELSE 0 END) approved, SUM(CASE WHEN latitude IS NOT NULL THEN 1 ELSE 0 END) with_coords, SUM(media_count) media, COUNT(DISTINCT idsp) species")
            ->groupBy('dataset_key');

        return DB::connection('chrysis')->table('gbif_datasets as d')
            ->leftJoinSub($counts, 'c', 'c.dataset_key', '=', 'd.dataset_key')
            ->select('d.*', 'c.records', 'c.approved', 'c.with_coords', 'c.media', 'c.species')
            ->selectRaw('CASE WHEN d.dataset_key IN ('.implode(',', array_fill(0, count(DatasetFetcher::HIGHLIGHT), '?')).') THEN 1 ELSE 0 END AS highlighted', array_keys(DatasetFetcher::HIGHLIGHT));
    }

    protected function columns(): array
    {
        return [
            Column::make('title', 'Dataset')->sortable('d.title')->searchable('d.title,d.dataset_key,d.citation')
                ->format(fn ($r) => (isset(DatasetFetcher::HIGHLIGHT[$r->dataset_key]) ? view('components.badge', ['color' => 'blue', 'slot' => DatasetFetcher::HIGHLIGHT[$r->dataset_key], 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render().' ' : '')
                    .'<a class="text-sky-700 hover:underline" target="_blank" rel="noopener" href="https://www.gbif.org/dataset/'.e($r->dataset_key).'">'.e($r->title ?: $r->dataset_key).'</a>', html: true),
            Column::make('records', 'Records')->sortable('c.records')->class('tabular-nums')->format(fn ($r) => number_format((int) $r->records)),
            Column::make('species', 'Species')->sortable('c.species')->class('tabular-nums'),
            Column::make('with_coords', 'With coords')->sortable('c.with_coords')->class('tabular-nums')->format(fn ($r) => number_format((int) $r->with_coords)),
            Column::make('approved', 'Approved')->sortable('c.approved')->class('tabular-nums')->format(fn ($r) => number_format((int) $r->approved)),
            Column::make('media', 'Images')->sortable('c.media')->class('tabular-nums')->format(fn ($r) => number_format((int) $r->media)),
            Column::make('license', 'Licence')->sortable('d.license')
                ->format(fn ($r) => str_replace(['http://creativecommons.org/licenses/', 'https://creativecommons.org/licenses/', 'http://creativecommons.org/publicdomain/zero/1.0/legalcode', '/legalcode'], ['', '', 'CC0', ''], (string) $r->license)),
            Column::make('doi', 'DOI')->format(fn ($r) => $r->doi ? '<a class="text-sky-700 hover:underline" href="https://doi.org/'.e($r->doi).'">'.e($r->doi).'</a>' : '—', html: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::boolean('highlighted', 'NHM / Lund', fn ($q, $v) => $v ? $q->whereIn('d.dataset_key', array_keys(DatasetFetcher::HIGHLIGHT)) : $q->whereNotIn('d.dataset_key', array_keys(DatasetFetcher::HIGHLIGHT))),
            Filter::select('license', 'Licence', [
                'http://creativecommons.org/publicdomain/zero/1.0/legalcode' => 'CC0', 'http://creativecommons.org/licenses/by/4.0/legalcode' => 'CC BY 4.0',
                'http://creativecommons.org/licenses/by-nc/4.0/legalcode' => 'CC BY-NC 4.0',
            ], 'd.license'),
        ];
    }

    protected function csvCitation(): ?string
    {
        return null;
    }

    public function render()
    {
        $highlights = DB::connection('chrysis')->table('gbif_datasets')->whereIn('dataset_key', array_keys(DatasetFetcher::HIGHLIGHT))->get()->keyBy('dataset_key');
        $counts = DB::connection('chrysis')->table('gbif_occurrences')->whereIn('dataset_key', array_keys(DatasetFetcher::HIGHLIGHT))
            ->selectRaw('dataset_key, COUNT(*) n, SUM(media_count) media, SUM(CASE WHEN type_status IS NOT NULL THEN 1 ELSE 0 END) types')->groupBy('dataset_key')->get()->keyBy('dataset_key');

        return view('livewire.data.datasets', ['highlights' => $highlights, 'hlCounts' => $counts, ...$this->tableData()]);
    }
}
