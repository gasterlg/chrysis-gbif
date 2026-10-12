<?php

namespace App\Gbif;

use App\Jobs\ImportSpeciesOccurrences;
use App\Models\GbifImportRun;
use Illuminate\Support\Facades\DB;

/**
 * Fetches GBIF occurrences for matched species (match.gbif_id > 0) and upserts them
 * into gbif_occurrences by gbif_id. Never touches the legacy `occurrences` table.
 */
class OccurrenceImporter
{
    public const PAGE = 300;

    public const MAX_OFFSET = 100000;

    public function __construct(private readonly GbifClient $gbif) {}

    /** Matched species (idsp => gbif key), optionally limited to some idsp values. */
    public function matchedSpecies(array $idsps = []): array
    {
        return DB::connection('chrysis')->table('match')
            ->where('gbif_id', '>', 0)
            ->when($idsps, fn ($q) => $q->whereIn('idsp', $idsps))
            ->orderBy('idsp')
            ->pluck('gbif_id', 'idsp')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** Create a run and queue one job per species. */
    public function start(array $idsps = [], ?int $limit = null, ?int $userId = null): GbifImportRun
    {
        $species = $this->matchedSpecies($idsps);

        $run = GbifImportRun::create([
            'type' => 'occurrences',
            'scope' => ['idsp' => array_keys($species), 'limit' => $limit],
            'status' => $species ? 'queued' : 'finished',
            'species_total' => count($species),
            'created_by' => $userId,
            'finished_at' => $species ? null : now(),
        ]);

        if ($userId === null) {
            \App\Models\AuditLog::record('gbif_import_started', null, 'cli', "run:{$run->id}", ['species' => count($species), 'limit' => $limit]);
        }

        foreach ($species as $idsp => $key) {
            ImportSpeciesOccurrences::dispatch($run->id, (int) $idsp, $key, $limit);
        }

        return $run;
    }

    /**
     * Import one species. Returns counters: fetched, inserted, updated, unchanged, with_coordinates, gbif_count.
     */
    public function importSpecies(int $idsp, int $taxonKey, ?int $limit = null, ?int $runId = null): array
    {
        $c = ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'with_coordinates' => 0, 'gbif_count' => 0, 'truncated' => false];
        $db = DB::connection('chrysis');
        $offset = 0;

        do {
            $pageSize = $limit !== null ? min(self::PAGE, $limit - $c['fetched']) : self::PAGE;
            if ($pageSize <= 0) {
                break;
            }
            if ($offset + $pageSize > self::MAX_OFFSET) {
                $c['truncated'] = true; // needs the Download API (phase 3)
                break;
            }

            $page = $this->gbif->searchOccurrences(['taxon_key' => $taxonKey], $pageSize, $offset, cache: false);
            $c['gbif_count'] = $page['count'];
            $rows = array_map(fn ($r) => OccurrenceMapper::toRow($r), $page['results']);
            $offset += count($page['results']);
            $c['fetched'] += count($rows);

            if ($rows) {
                $changed = [];
                $existing = $db->table('gbif_occurrences')->whereIn('gbif_id', array_column($rows, 'gbif_id'))->pluck('content_hash', 'gbif_id');
                $now = now();
                foreach ($rows as &$row) {
                    $old = $existing[$row['gbif_id']] ?? null;
                    $kind = $old === null ? 'inserted' : ($old === $row['content_hash'] ? 'unchanged' : 'updated');
                    $c[$kind]++;
                    if ($kind === 'updated') {
                        $changed[] = $row['gbif_id'];
                    }
                    $c['with_coordinates'] += $row['latitude'] !== null ? 1 : 0;
                    $row += ['idsp' => $idsp, 'import_run_id' => $runId, 'first_seen_at' => $now, 'last_seen_at' => $now, 'state' => 'pending', 'change_type' => 'new'];
                }
                unset($row);

                $update = array_values(array_diff(array_keys($rows[0]), ['gbif_id', 'first_seen_at', 'state', 'change_type']));
                foreach (array_chunk($rows, 100) as $chunk) {
                    $db->table('gbif_occurrences')->upsert($chunk, ['gbif_id'], $update);
                }
                if ($changed) {
                    // A changed record needs review again, even if it was approved before.
                    $db->table('gbif_occurrences')->whereIn('gbif_id', $changed)
                        ->update(['state' => 'pending', 'change_type' => 'changed', 'reviewed_by' => null, 'reviewed_at' => null]);
                }
            }
        } while (! $page['end_of_records'] && $page['results'] !== []);

        $c['flagged'] = app(OutlierFlagger::class)->flagSpecies($idsp);

        return $c;
    }
}
