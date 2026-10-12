<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\GbifImportRun;
use Illuminate\Console\Command;

class GbifBackfillHistory extends Command
{
    protected $signature = 'gbif:backfill-history';

    protected $description = 'Add History entries for import runs that predate import logging (idempotent)';

    public function handle(): int
    {
        $n = 0;
        foreach (GbifImportRun::where('type', 'occurrences')->whereNotNull('finished_at')->get() as $run) {
            if (AuditLog::where('action', 'gbif_import_finished')->where('target', "run:{$run->id}")->exists()) {
                continue;
            }
            $log = AuditLog::record('gbif_import_finished', $run->created_by, $run->created_by ? null : 'cli', "run:{$run->id}", $run->only(['status', 'species_total', 'fetched', 'inserted', 'updated', 'unchanged', 'with_coordinates']));
            $log->forceFill(['created_at' => $run->finished_at])->save();
            $n++;
        }
        $this->info("{$n} history entries added.");

        return self::SUCCESS;
    }
}
