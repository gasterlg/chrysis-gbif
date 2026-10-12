<?php

namespace App\Livewire\Review;

use App\Gbif\MatchApplier;
use App\Livewire\Concerns\WithDataTable;
use App\Models\GbifChange;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Applied changes')]
class Changes extends Component
{
    use WithDataTable;

    public function mount(): void
    {
        $this->authorize('update-gbif');
        if ($this->sortField === '') {
            $this->sortField = 'id';
            $this->sortDirection = 'desc';
        }
    }

    protected function baseQuery(): Builder
    {
        return GbifChange::query()->toBase();
    }

    protected function columns(): array
    {
        $snap = fn ($json) => $json ? e(($d = json_decode($json, true))['gbif_id'] ?? '—').' <span class="text-xs text-slate-500">'.e($d['gbif_species'] ?? '').'</span>' : '<span class="text-xs text-slate-400">(no row)</span>';

        return [
            Column::make('id', '#')->sortable()->class('tabular-nums'),
            Column::make('created_at', 'When')->sortable()->class('whitespace-nowrap tabular-nums'),
            Column::make('ref', 'IDSP')->searchable()->format(fn ($r) => '<a class="text-emerald-800 hover:underline" href="'.route('species.show', (int) $r->ref).'">'.e($r->ref).'</a>', html: true),
            Column::make('before', 'Before')->format(fn ($r) => $snap($r->before), html: true),
            Column::make('after', 'After')->format(fn ($r) => $snap($r->after), html: true),
            Column::make('reason', 'Reason')->searchable(),
            Column::make('username', 'By')->searchable(),
            Column::make('rolled_back_at', 'Rollback')->noExport()->class('text-right')
                ->format(fn ($r) => $r->rolled_back_at
                    ? '<span class="text-xs text-slate-500">rolled back '.e($r->rolled_back_at).'</span>'
                    : view('components.confirm-button', ['method' => 'rollback', 'args' => [(int) $r->id], 'title' => 'Roll back change #'.$r->id.'?',
                        'message' => 'Restores the match row for IDSP '.$r->ref.' to its previous value.', 'confirmLabel' => 'Roll back', 'danger' => true, 'variant' => null,
                        'slot' => 'Roll back', 'attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'text-xs'])])->render(), html: true),
        ];
    }

    protected function filters(): array
    {
        return [Filter::boolean('rolled', 'Rolled back', fn ($q, $v) => $v ? $q->whereNotNull('rolled_back_at') : $q->whereNull('rolled_back_at'))];
    }

    public function rollback(int $id): void
    {
        $this->authorize('update-gbif');
        try {
            app(MatchApplier::class)->rollback(GbifChange::findOrFail($id), auth()->user());
            $this->dispatch('toast', type: 'success', message: "Change #{$id} rolled back.");
        } catch (InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.review.changes', $this->tableData());
    }
}
