<?php

namespace App\Gbif;

use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class OccurrenceReviewer
{
    /** @return int rows changed */
    public function decide(array $gbifIds, string $decision, Account $user, ?int $idsp = null): int
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        $ids = array_values(array_unique(array_map('intval', $gbifIds)));
        if (! $ids) {
            return 0;
        }

        $n = DB::connection('chrysis')->transaction(function () use ($ids, $decision, $user) {
            $n = 0;
            foreach (array_chunk($ids, 500) as $chunk) {
                $n += DB::connection('chrysis')->table('gbif_occurrences')->whereIn('gbif_id', $chunk)
                    ->update(['state' => $decision, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            }

            return $n;
        });

        AuditLog::record('occurrences_'.$decision, $user->id, $user->username, $idsp ? "idsp:{$idsp}" : null, [
            'count' => $n, 'gbif_ids' => count($ids) <= 50 ? $ids : array_slice($ids, 0, 50),
        ]);

        return $n;
    }
}
