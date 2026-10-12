<?php

namespace App\Livewire\Concerns;

use App\Support\Column;
use App\Support\Filter;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reusable data table behaviour: search, sort, filters, pagination (query-string
 * synced), row selection with bulk actions, and streamed CSV export of the
 * current filtered query (with an optional CITATION.txt via csvCitation()).
 *
 * The component implements columns(), baseQuery() and optionally filters(),
 * bulkActions(), rowKey(), csvFilename(), csvCitation().
 * Bulk action "foo-bar" calls $this->bulkFooBar(array $ids).
 */
trait WithDataTable
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(except: [])]
    public array $filters = [];

    #[Url(as: 'per', except: 25)]
    public int $perPage = 25;

    /** Selected row keys (strings). */
    public array $selected = [];

    /** @return array<int, Column> */
    abstract protected function columns(): array;

    abstract protected function baseQuery(): Builder;

    /** @return array<int, Filter> */
    protected function filters(): array
    {
        return [];
    }

    /** @return array<string, string> key => label */
    protected function bulkActions(): array
    {
        return [];
    }

    protected function rowKey(): string
    {
        return 'id';
    }

    protected function csvFilename(): string
    {
        return Str::slug(class_basename(static::class)).'-'.now()->format('Ymd-His').'.csv';
    }

    /** Return text to ship a CITATION.txt alongside the CSV (S9 hook). */
    protected function csvCitation(): ?string
    {
        return null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $column = collect($this->columns())->first(fn (Column $c) => $c->key === $field && $c->sortable);
        if (! $column) {
            return;
        }

        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('filters', 'search');
        $this->resetPage();
    }

    public function runBulkAction(string $action): mixed
    {
        abort_unless(array_key_exists($action, $this->bulkActions()), 403);

        if ($this->selected === []) {
            $this->dispatch('toast', type: 'warning', message: 'Select at least one row first.');

            return null;
        }

        $method = 'bulk'.Str::studly($action);

        return $this->{$method}(array_values($this->selected));
    }

    /** Select or deselect all rows on the current page. */
    public function togglePage(array $keys): void
    {
        $keys = array_map('strval', $keys);
        $this->selected = array_values(array_diff($keys, $this->selected) === []
            ? array_diff($this->selected, $keys)
            : array_unique([...$this->selected, ...$keys]));
    }

    public function filteredQuery(): Builder
    {
        $query = $this->baseQuery();

        $term = trim($this->search);
        if ($term !== '') {
            $searchable = array_filter($this->columns(), fn (Column $c) => $c->searchable);
            $query->where(function ($q) use ($searchable, $term) {
                foreach (preg_split('/\s+/', $term) as $word) {
                    $q->where(function ($q) use ($searchable, $word) {
                        foreach ($searchable as $c) {
                            foreach (explode(',', $c->searchExpression ?? $c->key) as $expr) {
                                $q->orWhere(trim($expr), 'like', '%'.addcslashes($word, '%_\\').'%');
                            }
                        }
                    });
                }
            });
        }

        foreach ($this->filters() as $filter) {
            if ($filter->type === 'date_range') {
                $range = ['from' => $this->filters[$filter->key.'_from'] ?? null, 'to' => $this->filters[$filter->key.'_to'] ?? null];
                if ($range['from'] || $range['to']) {
                    ($filter->apply)($query, $range);
                }

                continue;
            }

            $value = $this->filters[$filter->key] ?? '';
            if ($value === '' || $value === null) {
                continue;
            }
            if ($filter->type === 'select' && ! array_key_exists($value, $filter->options)) {
                continue;
            }
            ($filter->apply)($query, $value);
        }

        $sort = collect($this->columns())->first(fn (Column $c) => $c->key === $this->sortField && $c->sortable);
        if ($sort) {
            foreach (explode(',', $sort->sortExpression ?? $sort->key) as $expr) {
                $query->orderBy(trim($expr), $this->sortDirection === 'desc' ? 'desc' : 'asc');
            }
        }

        return $query;
    }

    public function rows(): LengthAwarePaginator
    {
        return $this->filteredQuery()->paginate($this->perPage);
    }

    public function exportCsv(?array $onlyKeys = null): StreamedResponse
    {
        $columns = array_values(array_filter($this->columns(), fn (Column $c) => $c->export));
        $query = $this->filteredQuery();
        if ($onlyKeys !== null) {
            $query->whereIn($this->rowKeyColumn(), $onlyKeys);
        }
        $csvName = $this->csvFilename();
        $citation = $this->csvCitation();

        $writeCsv = function ($out) use ($columns, $query) {
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, array_map(fn (Column $c) => $c->label, $columns));
            foreach ($query->lazy(500) as $row) {
                fputcsv($out, array_map(fn (Column $c) => $c->exportValue($row), $columns));
            }
        };

        if ($citation === null) {
            return response()->streamDownload(function () use ($writeCsv) {
                $out = fopen('php://output', 'w');
                $writeCsv($out);
                fclose($out);
            }, $csvName, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($writeCsv, $csvName, $citation) {
            $tmp = tempnam(sys_get_temp_dir(), 'csv');
            $fh = fopen($tmp, 'w');
            $writeCsv($fh);
            fclose($fh);
            $zipPath = $tmp.'.zip';
            $zip = new \ZipArchive;
            $zip->open($zipPath, \ZipArchive::CREATE);
            $zip->addFile($tmp, $csvName);
            $zip->addFromString('CITATION.txt', $citation);
            $zip->close();
            readfile($zipPath);
            @unlink($tmp);
            @unlink($zipPath);
        }, Str::beforeLast($csvName, '.csv').'.zip', ['Content-Type' => 'application/zip']);
    }

    /** Column used for whereIn() on selected keys; override if rowKey() is an alias. */
    protected function rowKeyColumn(): string
    {
        return $this->rowKey();
    }

    /** Shared view data for <x-table>. */
    protected function tableData(): array
    {
        return [
            'rows' => $this->rows(),
            'columns' => $this->columns(),
            'tableFilters' => $this->filters(),
            'bulkActions' => $this->bulkActions(),
            'rowKey' => $this->rowKey(),
        ];
    }
}
