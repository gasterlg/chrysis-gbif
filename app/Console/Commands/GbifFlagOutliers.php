<?php

namespace App\Console\Commands;

use App\Gbif\OutlierFlagger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GbifFlagOutliers extends Command
{
    protected $signature = 'gbif:flag-outliers {--species=*}';

    protected $description = 'Recompute far_from_range / rare_country flags on gbif_occurrences';

    public function handle(OutlierFlagger $flagger): int
    {
        $ids = array_map('intval', array_filter($this->option('species')))
            ?: DB::connection('chrysis')->table('gbif_occurrences')->distinct()->pluck('idsp')->map(fn ($v) => (int) $v)->all();
        $flagged = 0;
        foreach ($ids as $id) {
            $flagged += $flagger->flagSpecies($id);
        }
        $this->info("{$flagged} records flagged across ".count($ids).' species.');

        return self::SUCCESS;
    }
}
