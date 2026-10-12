<?php

namespace App\Console\Commands;

use App\Gbif\OccurrenceImporter;
use Illuminate\Console\Command;

class GbifImport extends Command
{
    protected $signature = 'gbif:import
        {--species=* : _specie IDSP values (default: every matched species, match.gbif_id > 0)}
        {--limit= : Max records per species}';

    protected $description = 'Queue a GBIF occurrence import into gbif_occurrences (the legacy occurrences table is never touched)';

    public function handle(OccurrenceImporter $importer): int
    {
        $idsps = array_map('intval', array_filter($this->option('species')));
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $run = $importer->start($idsps, $limit);

        if ($run->species_total === 0) {
            $this->warn('No matched species found for that selection.');

            return self::FAILURE;
        }

        $this->info("Import run #{$run->id} queued for {$run->species_total} species".($limit ? " (max {$limit} records each)" : '').'.');
        $run->refresh();
        if (! $run->isActive()) {
            $this->line("Finished: fetched {$run->fetched}, inserted {$run->inserted}, updated {$run->updated}, unchanged {$run->unchanged}, with coordinates {$run->with_coordinates}.");
        } else {
            $this->line('Run a worker: php artisan queue:work --stop-when-empty');
        }

        return self::SUCCESS;
    }
}
