<?php

namespace App\Jobs;

use App\Gbif\OccurrenceImporter;
use App\Models\GbifImportRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportSpeciesOccurrences implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1; // GbifClient already retries each request

    public function __construct(public int $runId, public int $idsp, public int $taxonKey, public ?int $limit = null) {}

    public function handle(OccurrenceImporter $importer): void
    {
        $run = GbifImportRun::find($this->runId);
        if (! $run) {
            return;
        }
        GbifImportRun::whereKey($run->id)->where('status', 'queued')->update(['status' => 'running', 'started_at' => now()]);

        try {
            $counts = $importer->importSpecies($this->idsp, $this->taxonKey, $this->limit, $run->id);
            $note = $counts['truncated'] ? "idsp {$this->idsp}: stopped at 100,000 records (use the Download API)" : null;
            $run->addSpeciesResult($counts, $note);
        } catch (Throwable $e) {
            report($e);
            $run->addSpeciesResult([], "idsp {$this->idsp} (key {$this->taxonKey}): ".$e->getMessage());
        }
    }
}
