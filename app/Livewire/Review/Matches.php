<?php

namespace App\Livewire\Review;

use App\Gbif\GbifException;
use App\Gbif\MatchApplier;
use App\Livewire\Concerns\WithDataTable;
use App\Models\GbifMatchProposal;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Match review')]
class Matches extends Component
{
    use WithDataTable;

    public ?int $reviewingId = null;

    #[Validate('required|integer|min:1|max:999999999999')]
    public $manualKey = '';

    public function mount(): void
    {
        $this->authorize('update-gbif');
        if ($this->filters === []) {
            $this->filters = ['status' => 'pending'];
        }
        if ($this->sortField === '') {
            $this->sortField = 'priority';
        }
    }

    protected function baseQuery(): Builder
    {
        return GbifMatchProposal::query()
            ->select('*')
            ->selectRaw('CASE WHEN current_gbif_id IS NULL OR current_gbif_id = 0 THEN 0 ELSE 1 END AS priority')
            ->toBase();
    }

    protected function columns(): array
    {
        $badge = fn (string $text, string $color) => view('components.badge', ['color' => $color, 'slot' => $text, 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render();

        return [
            Column::make('query_name', 'Species')->searchable('query_name,usage_name,accepted_name')->sortable('query_name')
                ->format(fn ($r) => '<a class="font-medium text-emerald-800 hover:underline" href="'.route('species.show', $r->idsp).'"><em>'.e($r->query_name).'</em></a> <span class="text-xs text-slate-400">#'.$r->idsp.'</span>', html: true),
            Column::make('priority', 'Current')->sortable('priority,idsp')
                ->format(fn ($r) => $r->current_gbif_id ? e($r->current_gbif_id) : $badge($r->current_gbif_id === null ? 'no row' : 'gbif_id = 0', 'red'), html: true),
            Column::make('usage_name', 'GBIF match')
                ->format(fn ($r) => e($r->usage_name ?? '—').($r->rank ? ' <span class="text-xs text-slate-500">'.e($r->rank).' · '.e($r->taxon_status).'</span>' : '')
                    .($r->accepted_key && $r->accepted_key != $r->usage_key ? '<br><span class="text-xs text-amber-700">→ accepted: '.e($r->accepted_name).'</span>' : ''), html: true),
            Column::make('match_type', 'Type')->sortable('match_type,confidence')
                ->format(fn ($r) => $badge($r->match_type, match ($r->match_type) { 'EXACT' => 'green', 'FUZZY', 'VARIANT' => 'amber', default => 'red' }).' <span class="tabular-nums text-xs">'.e($r->confidence).'</span>', html: true),
            Column::make('proposed_key', 'Proposed key')->sortable()->class('tabular-nums')
                ->format(fn ($r) => $r->proposed_key ? '<a class="text-sky-700 hover:underline" target="_blank" rel="noopener" href="https://www.gbif.org/species/'.(int) $r->proposed_key.'">'.(int) $r->proposed_key.'</a>' : '—', html: true),
            Column::make('status', 'Status')->sortable()
                ->format(fn ($r) => $badge($r->status.($r->applied_key ? ' ('.$r->applied_key.')' : ''), match ($r->status) { 'accepted' => 'green', 'same' => 'slate', 'rejected', 'rolled_back' => 'red', default => 'amber' }), html: true),
            Column::make('actions', '')->noExport()->class('whitespace-nowrap text-right')
                ->format(fn ($r) => view('livewire.review.match-actions', ['r' => $r])->render(), html: true),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('status', 'Status', ['pending' => 'Pending', 'same' => 'Same as current', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'rolled_back' => 'Rolled back']),
            Filter::select('type', 'Match type', ['EXACT' => 'Exact', 'FUZZY' => 'Fuzzy', 'VARIANT' => 'Variant', 'HIGHERRANK' => 'Higher rank', 'NONE' => 'None'], 'match_type'),
            Filter::boolean('failed', 'Legacy failed (gbif_id = 0)', fn ($q, $v) => $v
                ? $q->where(fn ($q) => $q->whereNull('current_gbif_id')->orWhere('current_gbif_id', 0))
                : $q->where('current_gbif_id', '>', 0)),
        ];
    }

    protected function bulkActions(): array
    {
        return ['accept-exact' => 'Accept selected exact ≥95', 'reject' => 'Reject selected'];
    }

    private function applier(): MatchApplier
    {
        return app(MatchApplier::class);
    }

    public function accept(int $id): void
    {
        $this->authorize('update-gbif');
        $p = GbifMatchProposal::findOrFail($id);
        try {
            $change = $this->applier()->accept($p, auth()->user());
            $this->dispatch('toast', type: 'success', message: "{$p->query_name}: match set to {$p->proposed_key} (change #{$change->id}).");
        } catch (InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function acceptAlternative(int $id, int $key): void
    {
        $this->authorize('update-gbif');
        $p = GbifMatchProposal::findOrFail($id);
        $alt = collect($p->alternatives ?? [])->firstWhere('key', $key);
        abort_unless($alt, 404);
        $this->doManual($p, $alt['accepted_key'] ?? $key, 'alternative '.$key);
    }

    public function acceptManual(): void
    {
        $this->authorize('update-gbif');
        $this->validateOnly('manualKey');
        $p = GbifMatchProposal::findOrFail($this->reviewingId);
        $this->doManual($p, (int) $this->manualKey);
    }

    private function doManual(GbifMatchProposal $p, int $key, ?string $reason = null): void
    {
        try {
            $change = $this->applier()->acceptManualKey($p, auth()->user(), $key);
            $this->dispatch('toast', type: 'success', message: "{$p->query_name}: match set to {$change->after['gbif_id']}.");
            $this->dispatch('close-modal', name: 'review-proposal');
            $this->reset('reviewingId', 'manualKey');
        } catch (InvalidArgumentException $e) {
            $this->addError('manualKey', $e->getMessage());
        } catch (GbifException $e) {
            $this->addError('manualKey', 'GBIF is not reachable: '.$e->getMessage());
        }
    }

    public function reject(int $id): void
    {
        $this->authorize('update-gbif');
        $p = GbifMatchProposal::findOrFail($id);
        $this->applier()->reject($p, auth()->user());
        $this->dispatch('toast', type: 'success', message: "{$p->query_name}: proposal rejected.");
    }

    public function review(int $id): void
    {
        $this->reviewingId = $id;
        $this->reset('manualKey');
        $this->resetErrorBag();
        $this->dispatch('open-modal', name: 'review-proposal');
    }

    public function bulkAcceptExact(array $ids): void
    {
        $this->authorize('update-gbif');
        $done = $skipped = 0;
        foreach (GbifMatchProposal::query()->whereIn('id', array_map('intval', $ids))->get() as $p) {
            if (! $p->isBulkAcceptable()) {
                $skipped++;

                continue;
            }
            $this->applier()->accept($p, auth()->user(), reason: 'bulk exact');
            $done++;
        }
        $this->selected = [];
        $this->dispatch('toast', type: $done ? 'success' : 'warning', message: "{$done} accepted".($skipped ? ", {$skipped} skipped (not exact ≥95 / not pending)" : '').'.');
    }

    /** Accept every pending exact match with confidence ≥95 (header button, behind a confirm). */
    public function acceptAllExact(): void
    {
        $this->authorize('update-gbif');
        $ids = GbifMatchProposal::query()->where('status', 'pending')->where('match_type', 'EXACT')
            ->where('confidence', '>=', GbifMatchProposal::AUTO_CONFIDENCE)->whereNotNull('proposed_key')->pluck('id')->all();
        $this->bulkAcceptExact($ids);
    }

    public function bulkReject(array $ids): void
    {
        $this->authorize('update-gbif');
        $n = 0;
        foreach (GbifMatchProposal::query()->whereIn('id', array_map('intval', $ids))->where('status', 'pending')->get() as $p) {
            $this->applier()->reject($p, auth()->user());
            $n++;
        }
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: "{$n} rejected.");
    }

    public function render()
    {
        $counts = GbifMatchProposal::query()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');
        $exactReady = GbifMatchProposal::query()->where('status', 'pending')->where('match_type', 'EXACT')
            ->where('confidence', '>=', GbifMatchProposal::AUTO_CONFIDENCE)->whereNotNull('proposed_key')->count();

        return view('livewire.review.matches', [
            'counts' => $counts,
            'exactReady' => $exactReady,
            'reviewing' => $this->reviewingId ? GbifMatchProposal::find($this->reviewingId) : null,
            ...$this->tableData(),
        ]);
    }
}
