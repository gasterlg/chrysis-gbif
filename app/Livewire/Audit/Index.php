<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\WithDataTable;
use App\Models\AuditLog;
use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Login audit')]
class Index extends Component
{
    use WithDataTable;

    public function mount(): void
    {
        $this->authorize('admin');
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
        return [
            Column::make('created_at', 'When')->sortable()->class('whitespace-nowrap tabular-nums'),
            Column::make('action', 'Action')->sortable()->searchable(),
            Column::make('username', 'User')->sortable()->searchable(),
            Column::make('ip', 'IP')->searchable(),
            Column::make('target', 'Target')->searchable(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('action', 'Action', ['login' => 'login', 'login_failed' => 'login_failed', 'logout' => 'logout']),
            Filter::dateRange('created', 'Date', 'created_at'),
        ];
    }

    public function render()
    {
        return view('livewire.audit.index', $this->tableData());
    }
}
