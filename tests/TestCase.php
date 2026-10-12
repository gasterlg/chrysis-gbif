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
        if (! \Illuminate\Support\Facades\Schema::connection('chrysis')->hasTable('gbif_occurrences')) {
            // Tests without RefreshDatabase (or a reused default connection) still need the gbif_* tables.
            (require base_path('database/migrations/2026_10_12_000200_create_gbif_occurrence_tables.php'))->up();
        }

        config(['gbif.backoff_ms' => 1, 'gbif.rate_per_second' => 0]);
        Http::preventStrayRequests();
    }
}
