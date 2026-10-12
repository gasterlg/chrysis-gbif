<?php

namespace App\Console\Commands;

use App\Gbif\DatasetFetcher;
use Illuminate\Console\Command;

class GbifDatasets extends Command
{
    protected $signature = 'gbif:datasets {--all : Refresh datasets that were already fetched}';

    protected $description = 'Fetch GBIF dataset metadata (title, DOI, licence, citation) for datasets in gbif_occurrences';

    public function handle(DatasetFetcher $fetcher): int
    {
        $r = $fetcher->refresh(! $this->option('all'));
        $this->info("{$r['fetched']} datasets fetched, {$r['failed']} failed.");

        return self::SUCCESS;
    }
}
