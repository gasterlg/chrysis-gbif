<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GbifMatchProposal extends Model
{
    protected $connection = 'chrysis';

    protected $table = 'gbif_match_proposals';

    protected $guarded = [];

    protected $casts = ['alternatives' => 'array', 'reviewed_at' => 'datetime', 'checked_at' => 'datetime'];

    public const AUTO_CONFIDENCE = 95;

    /**
     * Exact, accepted species-level match that bulk-accept may apply. Synonyms are excluded:
     * GBIF can resolve them to another genus or even family (e.g. Cleptes nitidulus → Ormyrus nitidulus).
     */
    public function isBulkAcceptable(): bool
    {
        return $this->status === 'pending' && $this->match_type === 'EXACT' && $this->taxon_status === 'ACCEPTED'
            && (int) $this->confidence >= self::AUTO_CONFIDENCE && $this->proposed_key;
    }

    public function scopeBulkAcceptable($q)
    {
        return $q->where('status', 'pending')->where('match_type', 'EXACT')->where('taxon_status', 'ACCEPTED')
            ->where('confidence', '>=', self::AUTO_CONFIDENCE)->whereNotNull('proposed_key');
    }
}
