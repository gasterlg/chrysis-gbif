<?php

namespace App\Gbif;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\GbifChange;
use App\Models\GbifMatchProposal;
use App\Models\Species;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Writes approved match decisions to the legacy `match` table: one row, in a
 * transaction, with a before/after snapshot in gbif_changes for rollback.
 */
class MatchApplier
{
    public function __construct(private readonly GbifClient $gbif) {}

    public function accept(GbifMatchProposal $p, Account $user, ?int $key = null, ?string $name = null, string $reason = 'proposal'): GbifChange
    {
        $key ??= $p->proposed_key;
        $name ??= $p->proposed_name;
        if (! $key) {
            throw new InvalidArgumentException('Nothing to accept: the proposal has no species-level key.');
        }

        return DB::connection('chrysis')->transaction(function () use ($p, $user, $key, $name, $reason) {
            $db = DB::connection('chrysis');
            $before = $db->table('match')->where('idsp', $p->idsp)->lockForUpdate()->first();
            $species = Species::query()->findOrFail($p->idsp);

            $row = [
                'gbif_id' => $key,
                'gbif_species' => $name,
            ];
            if ($before) {
                $db->table('match')->where('idsp', $p->idsp)->update($row);
            } else {
                $db->table('match')->insert($row + [
                    'idsp' => $p->idsp,
                    'gen' => $species->GEN,
                    'sp' => Species::clean($species->SP),
                    'ssp' => Species::clean($species->SSP),
                ]);
            }
            $after = $db->table('match')->where('idsp', $p->idsp)->first();

            $change = GbifChange::create([
                'entity' => 'match', 'ref' => (string) $p->idsp,
                'before' => $before ? (array) $before : null, 'after' => (array) $after,
                'reason' => $reason, 'user_id' => $user->id, 'username' => $user->username,
            ]);

            $p->update(['status' => 'accepted', 'applied_key' => $key, 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'change_id' => $change->id]);
            AuditLog::record('match_accepted', $user->id, $user->username, "idsp:{$p->idsp}", [
                'change' => $change->id, 'from' => $before?->gbif_id, 'to' => $key, 'reason' => $reason,
            ]);

            return $change;
        });
    }

    /** Accept a key typed by the reviewer after checking it exists in GBIF (synonyms resolve to the accepted key). */
    public function acceptManualKey(GbifMatchProposal $p, Account $user, int $key): GbifChange
    {
        $taxon = $this->gbif->species($key);
        if (! $taxon) {
            throw new InvalidArgumentException("GBIF has no taxon with key {$key}.");
        }
        if ($taxon['accepted_key'] && $taxon['accepted_key'] !== $taxon['key']) {
            $accepted = $this->gbif->species($taxon['accepted_key']);
            $taxon = $accepted ?? $taxon;
        }

        return $this->accept($p, $user, $taxon['key'], $taxon['canonical_name'] ?? $taxon['name'], 'manual key '.$key);
    }

    public function reject(GbifMatchProposal $p, Account $user): void
    {
        $p->update(['status' => 'rejected', 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        AuditLog::record('match_rejected', $user->id, $user->username, "idsp:{$p->idsp}", ['proposed' => $p->proposed_key]);
    }

    /** Restore the before-image of one change (delete the row if it did not exist). */
    public function rollback(GbifChange $change, Account $user): void
    {
        if ($change->rolled_back_at) {
            throw new InvalidArgumentException('This change was already rolled back.');
        }
        if ($change->entity !== 'match') {
            throw new InvalidArgumentException('Unsupported entity.');
        }

        DB::connection('chrysis')->transaction(function () use ($change, $user) {
            $db = DB::connection('chrysis');
            $current = $db->table('match')->where('idsp', $change->ref)->lockForUpdate()->first();

            // Refuse if someone changed the row after this change was applied.
            if ($current && $change->after && (int) $current->gbif_id !== (int) $change->after['gbif_id']) {
                throw new InvalidArgumentException('The match row changed after this change; roll back the newer change first.');
            }

            if ($change->before === null) {
                $db->table('match')->where('idsp', $change->ref)->delete();
            } else {
                $restore = $change->before;
                unset($restore['idsp']);
                $db->table('match')->where('idsp', $change->ref)->update($restore);
            }

            $change->update(['rolled_back_at' => now(), 'rolled_back_by' => $user->id]);
            GbifMatchProposal::query()->where('change_id', $change->id)->update(['status' => 'rolled_back']);
            AuditLog::record('match_rolled_back', $user->id, $user->username, "idsp:{$change->ref}", ['change' => $change->id]);
        });
    }
}
