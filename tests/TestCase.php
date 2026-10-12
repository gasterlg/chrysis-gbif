<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\ChrysisFixture;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The chrysis connection is an in-memory SQLite DB (phpunit.xml). RefreshDatabase has
        // already created the gbif_* tables on it; add the legacy tables the app reads.
        ChrysisFixture::create();
        // RefreshDatabase does not always run the chrysis-connection migrations on the fresh
        // in-memory chrysis DB, so make sure each of them has been applied.
        $schema = \Illuminate\Support\Facades\Schema::connection('chrysis');
        foreach (['2026_10_12_000200_create_gbif_occurrence_tables' => 'gbif_occurrences', '2026_10_12_000300_create_gbif_review_tables' => 'gbif_match_proposals', '2026_10_12_000500_add_download_columns_to_gbif_import_runs' => null] as $file => $marker) {
            if ($marker === null ? ! $schema->hasColumn('gbif_import_runs', 'download_key') : ! $schema->hasTable($marker)) {
                (require base_path("database/migrations/{$file}.php"))->up();
            }
        }

        config(['gbif.backoff_ms' => 1, 'gbif.rate_per_second' => 0]);
        Http::preventStrayRequests();
    }
}
