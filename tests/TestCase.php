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

        // The legacy chrysis_1 connection becomes an in-memory SQLite DB with the same tables.
        config(['database.connections.chrysis' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        DB::purge('chrysis');
        ChrysisFixture::create();

        config(['gbif.backoff_ms' => 1, 'gbif.rate_per_second' => 0]);
        Http::preventStrayRequests();
    }
}
