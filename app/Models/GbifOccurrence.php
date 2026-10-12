<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GbifOccurrence extends Model
{
    protected $connection = 'chrysis';

    protected $table = 'gbif_occurrences';

    protected $primaryKey = 'gbif_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['media' => 'array', 'issues' => 'array', 'flags' => 'array', 'latitude' => 'float', 'longitude' => 'float'];

    /** Records of a GBIF taxon: the taxon itself or anything whose species is that key. */
    public function scopeForTaxon(Builder $q, int $key): Builder
    {
        return $q->where(fn ($q) => $q->where('taxon_key', $key)->orWhere('species_key', $key)->orWhere('accepted_taxon_key', $key));
    }
}
