<?php

namespace App\Gbif;

/** Maps one GBIF occurrence search result to a gbif_occurrences row. */
class OccurrenceMapper
{
    public static function toRow(array $r): array
    {
        $lat = self::coord($r['decimalLatitude'] ?? null, 90);
        $lon = self::coord($r['decimalLongitude'] ?? null, 180);
        if ($lat === null || $lon === null) {
            $lat = $lon = null;
        }
        $media = array_values(array_filter(array_map(fn ($m) => isset($m['identifier']) ? array_filter([
            'type' => $m['type'] ?? null, 'url' => $m['identifier'], 'license' => $m['license'] ?? null, 'creator' => $m['creator'] ?? $m['rightsHolder'] ?? null,
        ]) : null, $r['media'] ?? [])));

        $row = [
            'gbif_id' => (int) ($r['gbifID'] ?? $r['key']),
            'taxon_key' => self::int($r['taxonKey'] ?? null),
            'species_key' => self::int($r['speciesKey'] ?? null),
            'accepted_taxon_key' => self::int($r['acceptedTaxonKey'] ?? null),
            'dataset_key' => $r['datasetKey'] ?? null,
            'dataset_name' => self::str($r['datasetName'] ?? null, 255),
            'scientific_name' => self::str($r['scientificName'] ?? null, 255),
            'country_code' => self::str($r['countryCode'] ?? null, 2),
            'country' => self::str($r['country'] ?? null, 100),
            'state_province' => self::str($r['stateProvince'] ?? null, 255),
            'locality' => self::str($r['locality'] ?? null, 2000),
            'latitude' => $lat,
            'longitude' => $lon,
            'coord_uncertainty_m' => isset($r['coordinateUncertaintyInMeters']) ? (int) min(4294967295, max(0, $r['coordinateUncertaintyInMeters'])) : null,
            'event_date' => self::str($r['eventDate'] ?? null, 64),
            'year' => self::int($r['year'] ?? null),
            'month' => self::int($r['month'] ?? null),
            'basis_of_record' => self::str($r['basisOfRecord'] ?? null, 32),
            'recorded_by' => self::str($r['recordedBy'] ?? null, 2000),
            'identified_by' => self::str($r['identifiedBy'] ?? null, 2000),
            'institution_code' => self::str($r['institutionCode'] ?? null, 100),
            'collection_code' => self::str($r['collectionCode'] ?? null, 100),
            'catalog_number' => self::str($r['catalogNumber'] ?? null, 100),
            'type_status' => self::str(is_array($r['typeStatus'] ?? null) ? implode(', ', $r['typeStatus']) : ($r['typeStatus'] ?? null), 100),
            'sex' => self::str($r['sex'] ?? null, 32),
            'license' => self::str($r['license'] ?? null, 100),
            'media_count' => count($media),
            'media' => $media ? json_encode($media, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'issues' => ! empty($r['issues']) ? json_encode(array_values($r['issues'])) : null,
        ];
        $row['content_hash'] = sha1(json_encode($row));

        return $row;
    }

    private static function coord(mixed $v, int $max): ?float
    {
        return is_numeric($v) && abs((float) $v) <= $max ? round((float) $v, 6) : null;
    }

    private static function int(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private static function str(mixed $v, int $max): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return mb_substr((string) $v, 0, $max);
    }
}
