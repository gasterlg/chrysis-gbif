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

    /** Exact species-level match that bulk-accept may apply. */
    public function isBulkAcceptable(): bool
    {
        return $this->status === 'pending' && $this->match_type === 'EXACT'
            && (int) $this->confidence >= self::AUTO_CONFIDENCE && $this->proposed_key;
    }
}
