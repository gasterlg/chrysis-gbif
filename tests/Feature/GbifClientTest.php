<?php

namespace Tests\Feature;

use App\Gbif\GbifClient;
use App\Gbif\GbifException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GbifClientTest extends TestCase
{
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/gbif/{$name}.json")), true);
    }

    private function client(): GbifClient
    {
        return app(GbifClient::class);
    }

    public function test_species_match_is_parsed_and_sends_user_agent(): void
    {
        Http::fake(['api.gbif.org/v2/species/match*' => Http::response($this->fixture('match_cleptes_aerosus'))]);

        $m = $this->client()->matchSpecies('Cleptes aerosus', ['family' => 'Chrysididae']);

        $this->assertSame(7792353, $m['usage_key']);
        $this->assertSame('EXACT', $m['match_type']);
        $this->assertSame('ACCEPTED', $m['status']);
        $this->assertSame('Chrysididae', $m['family']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'scientificName=Cleptes%20aerosus')
            && str_contains($r->url(), 'family=Chrysididae')
            && str_starts_with($r->header('User-Agent')[0], 'chrysis.net-gbif'));
    }

    public function test_match_results_are_cached(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response($this->fixture('match_cleptes_aerosus'))]);

        $this->client()->matchSpecies('Cleptes aerosus');
        $this->client()->matchSpecies('Cleptes aerosus');

        Http::assertSentCount(1);
    }

    public function test_retries_on_429_and_5xx_then_succeeds(): void
    {
        Http::fake(['api.gbif.org/*' => Http::sequence()
            ->push('slow down', 429, ['Retry-After' => '0'])
            ->push('oops', 503)
            ->push($this->fixture('match_cleptes_aerosus'))]);

        $m = $this->client()->matchSpecies('Cleptes aerosus');

        $this->assertSame(7792353, $m['usage_key']);
        Http::assertSentCount(3);
    }

    public function test_gives_up_after_configured_retries(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response('down', 500)]);

        try {
            $this->client()->matchSpecies('Cleptes aerosus');
            $this->fail('expected GbifException');
        } catch (GbifException $e) {
            $this->assertSame(500, $e->getCode());
        }
        Http::assertSentCount(5);
    }

    public function test_does_not_retry_client_errors(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response('bad', 400)]);

        $this->expectException(GbifException::class);
        try {
            $this->client()->matchSpecies('x');
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_no_match_is_reported(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response(['diagnostics' => ['matchType' => 'NONE', 'confidence' => 100]])]);

        $m = $this->client()->matchSpecies('Nonexistentus fakeus');

        $this->assertNull($m['usage_key']);
        $this->assertSame('NONE', $m['match_type']);
    }

    public function test_occurrence_search_paging_and_limits(): void
    {
        Http::fake(['api.gbif.org/v1/occurrence/search*' => Http::response($this->fixture('occurrences_7792353'))]);

        $r = $this->client()->searchOccurrences(['taxon_key' => 7792353], 1000, 0);

        $this->assertSame(33, $r['count']);
        $this->assertCount(2, $r['results']);
        $this->assertSame('Croatia', $r['results'][0]['country']);
        Http::assertSent(fn (Request $req) => $req['limit'] === 300 && $req['taxon_key'] === 7792353);
    }

    public function test_occurrence_search_refuses_offsets_beyond_100k(): void
    {
        Http::fake();
        $this->expectException(GbifException::class);
        $this->client()->searchOccurrences(['taxon_key' => 7903], 300, 99900);
    }

    public function test_occurrence_count_uses_limit_zero(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response(['count' => 195000, 'results' => [], 'endOfRecords' => false])]);

        $this->assertSame(195000, $this->client()->occurrenceCount(7903));
        Http::assertSent(fn (Request $req) => $req['limit'] === 0);
    }
}
