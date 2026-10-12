<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GbifImportRun extends Model
{
    protected $connection = 'chrysis';

    protected $table = 'gbif_import_runs';

    protected $guarded = [];

    protected $casts = ['scope' => 'array', 'errors' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    /** Atomically add per-species counters and close the run when the last species is done. */
    public function addSpeciesResult(array $counts, ?string $error = null): void
    {
        DB::connection($this->connection)->transaction(function () use ($counts, $error) {
            $run = static::query()->lockForUpdate()->findOrFail($this->id);
            foreach (['fetched', 'inserted', 'updated', 'unchanged', 'with_coordinates'] as $k) {
                $run->{$k} += $counts[$k] ?? 0;
            }
            if ($error) {
                $run->errors = [...($run->errors ?? []), $error];
            }
            $run->species_done++;
            if ($run->species_done >= $run->species_total) {
                $run->status = ($run->errors && count($run->errors) >= $run->species_total) ? 'failed' : 'finished';
                $run->finished_at = now();
            }
            $run->save();
            $this->setRawAttributes($run->getAttributes(), true);
        });
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }
}
