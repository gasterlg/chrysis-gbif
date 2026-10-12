<?php

namespace App\Console\Commands;

use App\Gbif\GbifException;
use App\Gbif\MatchProposer;
use App\Jobs\ProposeMatch;
use App\Models\GbifMatchProposal;
use Illuminate\Console\Command;

class GbifMatch extends Command
{
    protected $signature = 'gbif:match
        {--species=* : _specie IDSP values (default: all)}
        {--failed-only : Only species whose legacy match is missing or gbif_id = 0}
        {--sync : Run now instead of queueing one job per species}';

    protected $description = 'Propose GBIF matches for _specie rows (failed ones first). Writes gbif_match_proposals only, never `match`.';

    public function handle(MatchProposer $proposer): int
    {
        $ids = $proposer->orderedSpecies(array_map('intval', array_filter($this->option('species'))), (bool) $this->option('failed-only'));

        if (! $this->option('sync')) {
            foreach ($ids as $id) {
                ProposeMatch::dispatch($id);
            }
            $this->info(count($ids).' match jobs queued (failed matches first).');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($ids));
        $errors = 0;
        foreach ($ids as $id) {
            try {
                $proposer->propose($id);
            } catch (GbifException $e) {
                $errors++;
                $this->newLine();
                $this->warn("idsp {$id}: {$e->getMessage()}");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $stats = GbifMatchProposal::query()->whereIn('idsp', $ids)->selectRaw('status, match_type, COUNT(*) n')->groupBy('status', 'match_type')->get();
        $this->table(['status', 'match type', 'n'], $stats->map(fn ($r) => [$r->status, $r->match_type, $r->n]));
        if ($errors) {
            $this->warn("{$errors} species failed (GBIF errors).");
        }

        return self::SUCCESS;
    }
}
