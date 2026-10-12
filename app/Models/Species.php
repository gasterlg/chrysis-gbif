<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Read-only view of chrysis_1._specie (MyISAM, legacy). */
class Species extends Model
{
    protected $connection = 'chrysis';

    protected $table = '_specie';

    protected $primaryKey = 'IDSP';

    public $timestamps = false;

    protected $guarded = ['*'];

    public function gbifMatch(): HasOne
    {
        return $this->hasOne(GbifMatch::class, 'idsp', 'IDSP');
    }

    /** Legacy columns use '0' for "empty". */
    public static function clean(?string $v): ?string
    {
        return ($v === null || $v === '' || $v === '0') ? null : $v;
    }

    public function getNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->GEN, self::clean($this->SP), self::clean($this->SSP)])));
    }

    public function getAuthorAttribute(): ?string
    {
        return self::clean($this->AUTH);
    }

    /** _specie joined with match and a computed gbif_status column. */
    public static function withMatchStatus(): Builder
    {
        return static::query()
            ->leftJoin('match as m', 'm.idsp', '=', '_specie.IDSP')
            ->select('_specie.IDSP', '_specie.SUBF', '_specie.GEN', '_specie.SP', '_specie.SSP', '_specie.AUTH',
                'm.gbif_id', 'm.gbif_species')
            ->selectRaw("CASE WHEN m.idsp IS NULL THEN 'none' WHEN m.gbif_id IS NULL OR m.gbif_id = 0 THEN 'failed' ELSE 'matched' END AS gbif_status");
    }
}
