<?php

namespace App\Livewire\Review;

use App\Livewire\Concerns\WithDataTable;
use App\Models\AuditLog;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One timeline of everything: logins, match decisions/rollbacks, occurrence approvals,
 * imports (UI and CLI), exports and user management. Source: gbif_audit_log.
 */
#[Title('History')]
class History extends Component
{
    use WithDataTable;

    public const CATEGORIES = [
        'auth' => ['login', 'login_failed', 'logout'],
        'match' => ['match_accepted', 'match_rejected', 'match_rolled_back'],
        'occurrences' => ['occurrences_approved', 'occurrences_rejected'],
        'import' => ['gbif_import_started', 'gbif_import_finished', 'gbif_download_requested'],
        'export' => ['export_occurrences'],
        'users' => ['user_created', 'user_updated', 'user_password_reset', 'user_disabled', 'user_enabled', 'user_update_gbif_granted', 'user_update_gbif_revoked'],
    ];

    public function mount(): void
    {
        $this->authorize('update-gbif');
        if ($this->sortField === '') {
            $this->sortField = 'created_at';
            $this->sortDirection = 'desc';
        }
    }

    protected function baseQuery(): Builder
    {
        return AuditLog::query()->toBase();
    }

    protected function columns(): array
    {
        $color = fn (string $a) => match (true) {
            str_starts_with($a, 'match') => 'blue',
            str_starts_with($a, 'occurrences') => 'green',
            str_contains($a, 'import') || str_contains($a, 'download') => 'amber',
            str_starts_with($a, 'user') => 'red',
            default => 'slate',
        };

        return [
            Column::make('created_at', 'When (UTC)')->sortable('created_at,id')->class('whitespace-nowrap tabular-nums'),
            Column::make('action', 'Action')->sortable()->searchable()
                ->format(fn ($r) => view('components.badge', ['color' => $color($r->action), 'slot' => str_replace('_', ' ', $r->action), 'attributes' => new \Illuminate\View\ComponentAttributeBag])->render(), html: true),
            Column::make('username', 'User')->sortable()->searchable(),
            Column::make('target', 'Target')->searchable()->format(fn ($r) => $this->targetLink($r->target), html: true),
            Column::make('meta', 'Details')->searchable()->class('max-w-md text-xs text-slate-600')
                ->format(fn ($r) => e(\Illuminate\Support\Str::limit($this->describe($r->meta), 160))),
            Column::make('ip', 'IP')->class('text-xs text-slate-500'),
        ];
    }

    private function targetLink(?string $target): string
    {
        if ($target && preg_match('/^idsp:(\d+)$/', $target, $m)) {
            return '<a class="text-emerald-800 hover:underline" href="'.route('species.show', (int) $m[1]).'">'.e($target).'</a>';
        }

        return e($target ?? '');
    }

    private function describe(?string $json): string
    {
        $d = json_decode($json ?? '', true);
        if (! is_array($d)) {
            return '';
        }

        return collect($d)->map(fn ($v, $k) => $k.': '.(is_array($v) ? implode(',', array_slice($v, 0, 8)).(count($v) > 8 ? '…' : '') : var_export($v, true)))->implode(' · ');
    }

    protected function filters(): array
    {
        return [
            Filter::select('category', 'Category', array_combine(array_keys(self::CATEGORIES), array_map('ucfirst', array_keys(self::CATEGORIES))))
                ->using(fn ($q, $v) => $q->whereIn('action', self::CATEGORIES[$v])),
            Filter::dateRange('date', 'Date', 'created_at'),
        ];
    }

    protected function csvFilename(): string
    {
        return 'chrysis-gbif-history-'.now()->format('Ymd-His').'.csv';
    }

    public function render()
    {
        return view('livewire.review.history', $this->tableData());
    }
}
