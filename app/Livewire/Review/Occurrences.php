<?php

namespace App\Livewire\Review;

use App\Gbif\OccurrenceReviewer;
use App\Livewire\Concerns\WithDataTable;
use App\Models\GbifOccurrence;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Import review')]
class Occurrences extends Component
{
    use WithDataTable;

    public function mount(): void
    {
        $this->authorize('update-gbif');
        $this->filters += ['state' => 'pending'];
        if ($this->sortField === '') {
            $this->sortField = 'flags';
            $this->sortDirection = 'desc';
        }
    }

    protected function rowKey(): string
    {
        return 'gbif_id';
    }

    protected function baseQuery(): Builder
    {
        return GbifOccurrence::query()->toBase();
    }

    private function speciesNames(): array
    {
        return DB::connection('chrysis')->table('gbif_occurrences as o')->join('_specie as s', 's.IDSP', '=', 'o.idsp')
            ->where('o.state', 'pending')
            ->groupBy('o.idsp', 's.GEN', 's.SP', 's.SSP')
            ->orderBy('s.GEN')->orderBy('s.SP')
            ->selectRaw('o.idsp, s.GEN, s.SP, s.SSP, COUNT(*) n')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->idsp => trim(implode(' ', array_filter([$r->GEN, \App\Models\Species::clean($r->SP), \App\Models\Species::clean($r->SSP)])))." ({$r->n})"])
            ->all();
    }

    protected function columns(): array
    {
        $badge = fn (string $t, string $c) => view('components.badge', ['color' => $c, 'slot' => $t, 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();

        return [
            Column::make('gbif_id', 'gbifID')->sortable()->class('tabular-nums')
                ->format(fn ($r) => '<a class="text-sky-700 hover:underline" target="_blank" rel="noopener" href="https://www.gbif.org/occurrence/'.(int) $r->gbif_id.'">'.(int) $r->gbif_id.'</a>', html: true),
            Column::make('scientific_name', 'Scientific name')->sortable()->searchable(),
            Column::make('country', 'Country')->sortable()->searchable('country,country_code'),
            Column::make('locality', 'Locality')->searchable('locality,state_province')->format(fn ($r) => \Illuminate\Support\Str::limit((string) $r->locality, 50)),
            Column::make('coordinates', 'Coordinates')->class('whitespace-nowrap tabular-nums')
                ->format(fn ($r) => $r->latitude !== null ? sprintf('%.4f, %.4f', $r->latitude, $r->longitude) : '—'),
            Column::make('event_date', 'Date')->sortable('year,event_date')->format(fn ($r) => $r->event_date ? substr($r->event_date, 0, 10) : ($r->year ?? '—')),
            Column::make('change_type', 'Change')->sortable()->format(fn ($r) => $badge($r->change_type, $r->change_type === 'changed' ? 'blue' : 'slate'), html: true),
            Column::make('flags', 'Flags')->sortable()
                ->format(fn ($r) => collect(json_decode($r->flags ?? '[]', true))->map(fn ($f) => $badge(str_replace('_', ' ', $f), 'red'))->implode(' ') ?: '', html: true),
            Column::make('state', 'State')->sortable()->format(fn ($r) => $badge($r->state, match ($r->state) { 'approved' => 'green', 'rejected' => 'red', default => 'amber' }), html: true),
            Column::make('actions', '')->noExport()->class('whitespace-nowrap text-right')
                ->format(fn ($r) => '<span class="inline-flex gap-1">'
                    .($r->state !== 'approved' ? '<button type="button" wire:click="decideOne('.(int) $r->gbif_id.', \'approved\')" class="rounded border border-emerald-600 px-2 py-0.5 text-xs text-emerald-800 hover:bg-emerald-50">Approve</button>' : '')
                    .($r->state !== 'rejected' ? '<button type="button" wire:click="decideOne('.(int) $r->gbif_id.', \'rejected\')" class="rounded border border-red-300 px-2 py-0.5 text-xs text-red-700 hover:bg-red-50">Reject</button>' : '')
                    .'</span>', html: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('idsp', 'Species (pending)', $this->speciesNames()),
            Filter::select('state', 'State', ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            Filter::select('change', 'Change', ['new' => 'New', 'changed' => 'Changed'], 'change_type'),
            Filter::boolean('flagged', 'Flagged', fn ($q, $v) => $v ? $q->whereNotNull('flags') : $q->whereNull('flags')),
        ];
    }

    protected function bulkActions(): array
    {
        return ['approve' => 'Approve selected', 'reject' => 'Reject selected'];
    }

    private function decide(array $ids, string $decision): void
    {
        $this->authorize('update-gbif');
        $n = app(OccurrenceReviewer::class)->decide($ids, $decision, auth()->user(), isset($this->filters['idsp']) && $this->filters['idsp'] !== '' ? (int) $this->filters['idsp'] : null);
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: "{$n} record(s) {$decision}.");
    }

    public function decideOne(int $gbifId, string $decision): void
    {
        $this->decide([$gbifId], $decision);
    }

    public function bulkApprove(array $ids): void
    {
        $this->decide($ids, 'approved');
    }

    public function bulkReject(array $ids): void
    {
        $this->decide($ids, 'rejected');
    }

    /** Approve every pending, unflagged record of the selected species. */
    public function approveUnflagged(): void
    {
        $this->authorize('update-gbif');
        $idsp = (int) ($this->filters['idsp'] ?? 0);
        abort_unless($idsp > 0, 422);
        $ids = GbifOccurrence::query()->where('idsp', $idsp)->where('state', 'pending')->whereNull('flags')->pluck('gbif_id')->all();
        $this->decide($ids, 'approved');
    }

    public function render()
    {
        $idsp = (int) ($this->filters['idsp'] ?? 0);
        $summary = DB::connection('chrysis')->table('gbif_occurrences')
            ->when($idsp, fn ($q) => $q->where('idsp', $idsp))
            ->selectRaw("SUM(CASE WHEN state = 'pending' THEN 1 ELSE 0 END) pending, SUM(CASE WHEN state = 'pending' AND flags IS NOT NULL THEN 1 ELSE 0 END) flagged,
                SUM(CASE WHEN state = 'pending' AND change_type = 'changed' THEN 1 ELSE 0 END) changed,
                SUM(CASE WHEN state = 'approved' THEN 1 ELSE 0 END) approved, SUM(CASE WHEN state = 'rejected' THEN 1 ELSE 0 END) rejected")
            ->first();
        $points = $idsp ? GbifOccurrence::query()->where('idsp', $idsp)->where('state', 'pending')->whereNotNull('latitude')->limit(5000)
            ->get(['gbif_id', 'latitude', 'longitude', 'country', 'locality', 'year', 'flags'])
            ->map(fn ($o) => [(float) $o->latitude, (float) $o->longitude, (int) $o->gbif_id, trim(($o->locality ?? '').' ('.$o->country.')'), $o->year, $o->flags ? 1 : 0])->all() : [];

        return view('livewire.review.occurrences', ['summary' => $summary, 'idsp' => $idsp, 'points' => $points, ...$this->tableData()]);
    }
}
