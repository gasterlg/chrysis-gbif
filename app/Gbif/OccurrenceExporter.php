<?php

namespace App\Gbif;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Approved gbif_occurrences as a zip: occurrences.csv + datasets.csv + CITATION.txt. */
class OccurrenceExporter
{
    public const COLUMNS = [
        'gbif_id' => 'gbifID', 'idsp' => 'idsp', 'scientific_name' => 'scientificName', 'taxon_key' => 'taxonKey',
        'country_code' => 'countryCode', 'country' => 'country', 'state_province' => 'stateProvince', 'locality' => 'locality',
        'latitude' => 'decimalLatitude', 'longitude' => 'decimalLongitude', 'coord_uncertainty_m' => 'coordinateUncertaintyInMeters',
        'event_date' => 'eventDate', 'year' => 'year', 'month' => 'month', 'basis_of_record' => 'basisOfRecord',
        'recorded_by' => 'recordedBy', 'identified_by' => 'identifiedBy', 'institution_code' => 'institutionCode',
        'collection_code' => 'collectionCode', 'catalog_number' => 'catalogNumber', 'type_status' => 'typeStatus', 'sex' => 'sex',
        'dataset_key' => 'datasetKey', 'license' => 'license', 'media_count' => 'mediaCount',
    ];

    public function query(?int $idsp = null)
    {
        return DB::connection('chrysis')->table('gbif_occurrences')->where('state', 'approved')
            ->when($idsp, fn ($q) => $q->where('idsp', $idsp))->orderBy('gbif_id');
    }

    public function citation(?int $idsp, array $datasetKeys): string
    {
        $date = now()->format('d F Y');
        $scope = $idsp ? "species IDSP {$idsp}" : 'all species';
        $sets = DB::connection('chrysis')->table('gbif_datasets')->whereIn('dataset_key', $datasetKeys)->get()->keyBy('dataset_key');
        $lines = collect($datasetKeys)->map(function ($k) use ($sets) {
            $d = $sets[$k] ?? null;
            if (! $d) {
                return "- {$k} (metadata not fetched; see https://www.gbif.org/dataset/{$k})";
            }

            return '- '.($d->citation ?: $d->title).($d->doi ? " https://doi.org/{$d->doi}" : '')." [{$k}]".($d->license ? " Licence: {$d->license}" : '');
        });

        return "Approved GBIF occurrence records exported from chrysis.net ({$scope}) on {$date}.\n\n"
            ."These records were retrieved through the GBIF.org occurrence search API and reviewed on chrysis.net.\n"
            ."For publications, GBIF recommends citing a download DOI (Download API) rather than individual datasets.\n"
            ."Respect each dataset's licence (CC BY-NC records may not be used commercially).\n\n"
            .'Datasets ('.count($datasetKeys)."):\n".$lines->implode("\n")."\n";
    }

    public function download(?int $idsp = null): StreamedResponse
    {
        $name = 'chrysis-gbif-approved-'.($idsp ? "idsp{$idsp}-" : 'all-').now()->format('Ymd-His');

        return response()->streamDownload(function () use ($idsp) {
            $tmp = tempnam(sys_get_temp_dir(), 'occ');
            $fh = fopen($tmp, 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, array_values(self::COLUMNS));
            $datasets = [];
            foreach ($this->query($idsp)->select(array_keys(self::COLUMNS))->lazyById(1000, 'gbif_id') as $r) {
                fputcsv($fh, array_map(fn ($c) => $r->{$c}, array_keys(self::COLUMNS)));
                if ($r->dataset_key) {
                    $datasets[$r->dataset_key] = ($datasets[$r->dataset_key] ?? 0) + 1;
                }
            }
            fclose($fh);
            ksort($datasets);

            $dsCsv = fopen('php://temp', 'w+');
            fputcsv($dsCsv, ['datasetKey', 'records', 'title', 'doi', 'license']);
            $meta = DB::connection('chrysis')->table('gbif_datasets')->whereIn('dataset_key', array_keys($datasets))->get()->keyBy('dataset_key');
            foreach ($datasets as $k => $n) {
                fputcsv($dsCsv, [$k, $n, $meta[$k]->title ?? '', $meta[$k]->doi ?? '', $meta[$k]->license ?? '']);
            }
            rewind($dsCsv);

            $zipPath = $tmp.'.zip';
            $zip = new \ZipArchive;
            $zip->open($zipPath, \ZipArchive::CREATE);
            $zip->addFile($tmp, 'occurrences.csv');
            $zip->addFromString('datasets.csv', stream_get_contents($dsCsv));
            $zip->addFromString('CITATION.txt', $this->citation($idsp, array_keys($datasets)));
            $zip->close();
            readfile($zipPath);
            @unlink($tmp);
            @unlink($zipPath);
        }, $name.'.zip', ['Content-Type' => 'application/zip']);
    }
}
