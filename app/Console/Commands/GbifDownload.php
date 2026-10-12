<?php

namespace App\Console\Commands;

use App\Gbif\DownloadClient;
use App\Gbif\GbifException;
use App\Models\AuditLog;
use App\Models\GbifImportRun;
use Illuminate\Console\Command;

class GbifDownload extends Command
{
    protected $signature = 'gbif:download {--taxon=* : GBIF taxon keys (default: Chrysididae 7903)} {--status= : Poll the status of an existing download run id}';

    protected $description = 'Request (or poll) a GBIF Download API export with a citable DOI. Needs GBIF credentials in .env.';

    public function handle(DownloadClient $client): int
    {
        if ($id = $this->option('status')) {
            $run = $client->poll(GbifImportRun::where('type', 'download')->findOrFail((int) $id));
            $this->info("Download {$run->download_key}: {$run->download_status}".($run->download_doi ? " · DOI {$run->download_doi}" : '').($run->download_link ? " · {$run->download_link}" : ''));

            return self::SUCCESS;
        }

        try {
            $run = $client->request(array_map('intval', $this->option('taxon')));
        } catch (GbifException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        AuditLog::record('gbif_download_requested', null, 'cli', null, ['run' => $run->id, 'key' => $run->download_key]);
        $this->info("Download requested: {$run->download_key} (run #{$run->id}). Poll with: php artisan gbif:download --status={$run->id}");

        return self::SUCCESS;
    }
}
