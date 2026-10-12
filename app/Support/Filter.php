<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Builder;

/** Filter definition for WithDataTable: select, boolean or date range. */
final class Filter
{
    public array $options = [];

    public ?Closure $apply = null;

    public function __construct(public string $key, public string $label, public string $type) {}

    public static function select(string $key, string $label, array $options, ?string $column = null): self
    {
        $f = new self($key, $label, 'select');
        $f->options = $options;
        $f->apply = fn (Builder $q, $value) => $q->where($column ?? $key, $value);

        return $f;
    }

    public static function boolean(string $key, string $label, ?Closure $apply = null): self
    {
        $f = new self($key, $label, 'boolean');
        $f->apply = $apply ?? fn (Builder $q, $value) => $q->where($key, (bool) $value);

        return $f;
    }

    /** Values arrive as filters[key_from] / filters[key_to] (Y-m-d). */
    public static function dateRange(string $key, string $label, ?string $column = null): self
    {
        $f = new self($key, $label, 'date_range');
        $f->apply = function (Builder $q, array $range) use ($column, $key) {
            if (! empty($range['from'])) {
                $q->whereDate($column ?? $key, '>=', $range['from']);
            }
            if (! empty($range['to'])) {
                $q->whereDate($column ?? $key, '<=', $range['to']);
            }
        };

        return $f;
    }

    /** Custom query: fn (Builder $q, $value). */
    public function using(Closure $apply): self
    {
        $this->apply = $apply;

        return $this;
    }
}
