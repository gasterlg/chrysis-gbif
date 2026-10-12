<?php

namespace App\Gbif;

use App\Models\GbifImportRun;
use Illuminate\Support\Facades\Http;

/**
 * GBIF Download API (asynchronous DwC-A/SIMPLE_CSV with a citable DOI). Needs a GBIF.org
 * account: GBIF_DOWNLOAD_USER / GBIF_DOWNLOAD_PASSWORD / GBIF_DOWNLOAD_EMAIL. Disabled without them.
 */
class DownloadClient
{
    public static function configured(): bool
    {
        return filled(config('gbif.download.user')) && filled(config('gbif.download.password'));
    }

    /** Request a download for taxon keys (default: the whole family). Returns the run that tracks it. */
    public function request(array $taxonKeys = [], ?int $userId = null): GbifImportRun
    {
        if (! self::configured()) {
            throw new GbifException('GBIF download credentials are not configured (GBIF_DOWNLOAD_USER / GBIF_DOWNLOAD_PASSWORD).');
        }
        $keys = $taxonKeys ?: [(int) config('gbif.chrysididae_key')];
        $body = [
            'creator' => config('gbif.download.user'),
            'notificationAddresses' => array_filter([config('gbif.download.email')]),
            'sendNotification' => filled(config('gbif.download.email')),
            'format' => 'SIMPLE_CSV',
            'predicate' => ['type' => 'in', 'key' => 'TAXON_KEY', 'values' => array_map('strval', $keys)],
        ];

        $res = Http::baseUrl(config('gbif.base_url'))->withUserAgent(config('gbif.user_agent'))
            ->withBasicAuth(config('gbif.download.user'), config('gbif.download.password'))
            ->timeout(60)->post('/v1/occurrence/download/request', $body);
        if (! $res->successful()) {
            throw new GbifException('GBIF download request failed: HTTP '.$res->status().' '.$res->body(), $res->status());
        }

        return GbifImportRun::create([
            'type' => 'download', 'scope' => ['taxon_keys' => $keys], 'status' => 'running',
            'download_key' => trim($res->body()), 'download_status' => 'PREPARING', 'created_by' => $userId, 'started_at' => now(),
        ]);
    }

    /** Poll the status of a download run (no credentials needed). */
    public function poll(GbifImportRun $run): GbifImportRun
    {
        $json = Http::baseUrl(config('gbif.base_url'))->withUserAgent(config('gbif.user_agent'))->timeout(30)
            ->get("/v1/occurrence/download/{$run->download_key}")->throw()->json();
        $status = $json['status'] ?? 'UNKNOWN';
        $run->update([
            'download_status' => $status,
            'download_doi' => $json['doi'] ?? null,
            'download_link' => $json['downloadLink'] ?? null,
            'download_records' => $json['totalRecords'] ?? null,
            'status' => match ($status) { 'SUCCEEDED' => 'finished', 'FAILED', 'KILLED', 'CANCELLED' => 'failed', default => 'running' },
            'finished_at' => in_array($status, ['SUCCEEDED', 'FAILED', 'KILLED', 'CANCELLED'], true) ? now() : null,
        ]);

        return $run;
    }
}
