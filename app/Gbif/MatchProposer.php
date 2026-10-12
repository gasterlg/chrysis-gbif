<?php

namespace App\Gbif;

use App\Models\GbifMatchProposal;
use App\Models\Species;
use Illuminate\Support\Facades\DB;

/** Runs a GBIF v2 match for one _specie row and stores a proposal. Never writes to `match`. */
class MatchProposer
{
    /** Ranks that are a valid target for a chrysis.net species row. */
    public const SPECIES_RANKS = ['SPECIES', 'SUBSPECIES', 'VARIETY', 'FORM'];

    public function __construct(private readonly GbifClient $gbif) {}

    /** IDSPs ordered so species whose legacy lookup failed (gbif_id = 0 / no row) come first. */
    public function orderedSpecies(array $only = [], bool $failedOnly = false): array
    {
        return DB::connection('chrysis')->table('_specie as s')
            ->leftJoin('match as m', 'm.idsp', '=', 's.IDSP')
            ->when($only, fn ($q) => $q->whereIn('s.IDSP', $only))
            ->when($failedOnly, fn ($q) => $q->where(fn ($q) => $q->whereNull('m.gbif_id')->orWhere('m.gbif_id', 0)))
            ->orderByRaw('CASE WHEN m.gbif_id IS NULL OR m.gbif_id = 0 THEN 0 ELSE 1 END')
            ->orderBy('s.IDSP')
            ->pluck('s.IDSP')->map(fn ($v) => (int) $v)->all();
    }

    public function propose(int $idsp): GbifMatchProposal
    {
        $species = Species::query()->findOrFail($idsp);
        $name = $species->name;
        $current = DB::connection('chrysis')->table('match')->where('idsp', $idsp)->value('gbif_id');
        $current = $current === null ? null : (int) $current;

        $m = $this->gbif->matchSpecies($name, ['family' => 'Chrysididae']);

        $speciesLevel = in_array($m['rank'], self::SPECIES_RANKS, true) && in_array($m['match_type'], ['EXACT', 'FUZZY', 'VARIANT'], true);
        // Occurrence search follows the accepted taxon, so a synonym proposes its accepted key.
        $proposedKey = $speciesLevel ? ($m['accepted_key'] ?? $m['usage_key']) : null;
        $proposedName = $speciesLevel ? ($m['accepted_key'] ? ($m['accepted_canonical_name'] ?? $m['accepted_name']) : $m['canonical_name']) : null;

        $attrs = [
            'query_name' => $name,
            'current_gbif_id' => $current,
            'usage_key' => $m['usage_key'],
            'usage_name' => $m['name'],
            'rank' => $m['rank'],
            'taxon_status' => $m['status'],
            'accepted_key' => $m['accepted_key'],
            'accepted_name' => $m['accepted_name'],
            'match_type' => $m['match_type'],
            'confidence' => $m['confidence'],
            'proposed_key' => $proposedKey,
            'proposed_name' => $proposedName,
            'alternatives' => $m['alternatives'] ?: null,
            'note' => $m['note'] ? mb_substr($m['note'], 0, 255) : null,
            'checked_at' => now(),
        ];

        $existing = GbifMatchProposal::query()->where('idsp', $idsp)->first();
        // Keep reviewer decisions unless GBIF now says something different.
        $decided = $existing && in_array($existing->status, ['accepted', 'rejected'], true);
        $sameAsBefore = $existing && (int) $existing->proposed_key === (int) $proposedKey && $existing->match_type === $m['match_type'];

        if ($decided && $sameAsBefore) {
            $existing->update(['checked_at' => now(), 'current_gbif_id' => $current, 'alternatives' => $attrs['alternatives']]);

            return $existing;
        }

        $attrs['status'] = ($proposedKey && $current === $proposedKey) ? 'same' : 'pending';
        $attrs += ['reviewed_by' => null, 'reviewed_at' => null, 'change_id' => null, 'applied_key' => null];

        return GbifMatchProposal::query()->updateOrCreate(['idsp' => $idsp], $attrs);
    }
}
