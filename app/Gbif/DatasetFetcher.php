<?php

namespace App\Gbif;

use Illuminate\Support\Facades\DB;

/** Fills gbif_datasets from GBIF dataset metadata for every dataset key seen in gbif_occurrences. */
class DatasetFetcher
{
    public const HIGHLIGHT = [
        '7e380070-f762-11e1-a439-00145eb45e9a' => 'NHM London',
        '2356f899-6244-4bbb-aa33-50e29132e86a' => 'Lund (inventory)',
        'b23c2479-214f-4160-9f98-bd3831ab131b' => 'Lund (specimens)',
    ];

    public function __construct(private readonly GbifClient $gbif) {}

    /** @return array{fetched:int, failed:int} */
    public function refresh(bool $onlyMissing = true): array
    {
        $db = DB::connection('chrysis');
        $keys = $db->table('gbif_occurrences')->whereNotNull('dataset_key')->distinct()->pluck('dataset_key')
            ->merge(array_keys(self::HIGHLIGHT))->unique();
        if ($onlyMissing) {
            $keys = $keys->diff($db->table('gbif_datasets')->whereNotNull('fetched_at')->pluck('dataset_key'));
        }

        $fetched = $failed = 0;
        foreach ($keys as $key) {
            try {
                $d = $this->gbif->dataset($key);
            } catch (GbifException $e) {
                $failed++;

                continue;
            }
            $db->table('gbif_datasets')->updateOrInsert(['dataset_key' => $key], [
                'title' => mb_substr((string) ($d['title'] ?? ''), 0, 255),
                'doi' => $d['doi'] ?? null,
                'license' => $d['license'] ?? null,
                'publisher' => null,
                'publisher_key' => $d['publishingOrganizationKey'] ?? null,
                'citation' => $d['citation']['text'] ?? null,
                'url' => $d['homepage'] ?? null,
                'fetched_at' => now(),
            ]);
            $fetched++;
        }

        return compact('fetched', 'failed');
    }
}
