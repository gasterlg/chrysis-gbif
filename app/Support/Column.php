<?php

namespace App\Support;

use Closure;

/** Column definition for <x-table> / WithDataTable. */
final class Column
{
    public ?Closure $format = null;

    public bool $html = false;

    public bool $sortable = false;

    public bool $searchable = false;

    public ?string $sortExpression = null;

    public ?string $searchExpression = null;

    public string $class = '';

    public bool $export = true;

    public function __construct(public string $key, public string $label) {}

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    public function sortable(?string $expression = null): self
    {
        $this->sortable = true;
        $this->sortExpression = $expression;

        return $this;
    }

    public function searchable(?string $expression = null): self
    {
        $this->searchable = true;
        $this->searchExpression = $expression;

        return $this;
    }

    /** fn ($row) => string; set $html=true to render unescaped (the closure must escape). */
    public function format(Closure $format, bool $html = false): self
    {
        $this->format = $format;
        $this->html = $html;

        return $this;
    }

    public function class(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    public function noExport(): self
    {
        $this->export = false;

        return $this;
    }

    public function value(mixed $row): mixed
    {
        return $this->format ? ($this->format)($row) : data_get($row, $this->key);
    }

    public function exportValue(mixed $row): string
    {
        $v = $this->html ? data_get($row, $this->key) : $this->value($row);

        return is_scalar($v) || $v === null ? (string) $v : json_encode($v);
    }
}
