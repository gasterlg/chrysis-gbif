<?php

namespace App\Gbif;

use Illuminate\Support\Facades\DB;

/**
 * Flags gbif_occurrences of one species that look unusual:
 *  - far_from_range: more than max(MIN_KM, FACTOR × median distance) from the species' median point
 *  - rare_country: a country holding < 5 % (and at most 2) of the species' records, when it has ≥ 20
 *  - no_coordinates is NOT a flag (many valid museum records lack them).
 */
class OutlierFlagger
{
    public const MIN_KM = 1500;

    public const FACTOR = 4;

    public function flagSpecies(int $idsp): int
    {
        $db = DB::connection('chrysis');
        $rows = $db->table('gbif_occurrences')->where('idsp', $idsp)->get(['gbif_id', 'latitude', 'longitude', 'country_code']);
        if ($rows->isEmpty()) {
            return 0;
        }

        $flags = [];
        $pts = $rows->filter(fn ($r) => $r->latitude !== null);
        if ($pts->count() >= 5) {
            $mLat = self::median($pts->pluck('latitude')->map(fn ($v) => (float) $v)->all());
            $mLon = self::median($pts->pluck('longitude')->map(fn ($v) => (float) $v)->all());
            $dist = $pts->mapWithKeys(fn ($r) => [$r->gbif_id => self::km($mLat, $mLon, (float) $r->latitude, (float) $r->longitude)]);
            $limit = max(self::MIN_KM, self::FACTOR * self::median($dist->values()->all()));
            foreach ($dist as $id => $d) {
                if ($d > $limit) {
                    $flags[$id][] = 'far_from_range';
                }
            }
        }

        $total = $rows->count();
        if ($total >= 20) {
            $byCountry = $rows->whereNotNull('country_code')->countBy('country_code');
            foreach ($rows as $r) {
                $n = $r->country_code ? $byCountry[$r->country_code] : null;
                if ($n !== null && $n <= 2 && $n / $total < 0.05) {
                    $flags[$r->gbif_id][] = 'rare_country';
                }
            }
        }

        $db->table('gbif_occurrences')->where('idsp', $idsp)->update(['flags' => null]);
        foreach ($flags as $id => $f) {
            $db->table('gbif_occurrences')->where('gbif_id', $id)->update(['flags' => json_encode(array_values(array_unique($f)))]);
        }

        return count($flags);
    }

    public static function median(array $v): float
    {
        sort($v);
        $n = count($v);

        return $n === 0 ? 0.0 : ($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2);
    }

    public static function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = deg2rad(...);
        $a = sin(($r($lat2) - $r($lat1)) / 2) ** 2 + cos($r($lat1)) * cos($r($lat2)) * sin(($r($lon2) - $r($lon1)) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
    }
}
