<?php

namespace Tests\Feature;

use App\Gbif\OccurrenceImporter;
use App\Gbif\OccurrenceMapper;
use App\Jobs\ImportSpeciesOccurrences;
use App\Livewire\Species\Show;
use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\GbifImportRun;
use App\Models\GbifOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class GbifImportTest extends TestCase
{
    use RefreshDatabase;

    private function record(int $id, array $extra = []): array
    {
        return $extra + [
            'key' => $id, 'gbifID' => (string) $id, 'taxonKey' => 7792353, 'speciesKey' => 7792353, 'acceptedTaxonKey' => 7792353,
            'datasetKey' => '7e380070-f762-11e1-a439-00145eb45e9a', 'scientificName' => 'Cleptes aerosus Förster, 1853',
            'countryCode' => 'HR', 'country' => 'Croatia', 'locality' => 'Učka', 'decimalLatitude' => 45.2871, 'decimalLongitude' => 14.2034,
            'coordinateUncertaintyInMeters' => 250, 'eventDate' => '2021-06-12', 'year' => 2021, 'month' => 6,
            'basisOfRecord' => 'PRESERVED_SPECIMEN', 'institutionCode' => 'NHMUK', 'catalogNumber' => 'NHMUK010101',
            'license' => 'http://creativecommons.org/licenses/by/4.0/legalcode',
            'media' => [['type' => 'StillImage', 'identifier' => 'https://example.test/a.jpg']],
        ];
    }

    private function fakeGbif(array $pages): void
    {
        $seq = Http::sequence();
        foreach ($pages as $p) {
            $seq->push($p);
        }
        Http::fake(['api.gbif.org/v1/occurrence/search*' => $seq]);
    }

    public function test_mapper_keeps_real_coordinates_and_media(): void
    {
        $row = OccurrenceMapper::toRow($this->record(5001234567));

        $this->assertSame(5001234567, $row['gbif_id']);          // > 2^31: needs BIGINT
        $this->assertSame(45.2871, $row['latitude']);
        $this->assertSame(14.2034, $row['longitude']);
        $this->assertSame(1, $row['media_count']);
        $this->assertSame('NHMUK010101', $row['catalog_number']);
        $this->assertSame(40, strlen($row['content_hash']));

        $bad = OccurrenceMapper::toRow($this->record(1, ['decimalLatitude' => 123.0]));
        $this->assertNull($bad['latitude']);
        $this->assertNull($bad['longitude']);
    }

    public function test_import_pages_upserts_by_gbif_id_and_never_touches_legacy_table(): void
    {
        $legacyBefore = DB::connection('chrysis')->table('occurrences')->get();

        $this->fakeGbif([
            ['count' => 3, 'endOfRecords' => false, 'offset' => 0, 'limit' => 300, 'results' => [$this->record(5001234567), $this->record(5001234568, ['decimalLatitude' => null, 'decimalLongitude' => null])]],
            ['count' => 3, 'endOfRecords' => true, 'offset' => 2, 'limit' => 300, 'results' => [$this->record(5001234569)]],
            // second run
            ['count' => 3, 'endOfRecords' => true, 'results' => [$this->record(5001234567, ['locality' => 'Učka, Poklon']), $this->record(5001234568, ['decimalLatitude' => null, 'decimalLongitude' => null]), $this->record(5001234569)]],
        ]);

        $run = app(OccurrenceImporter::class)->start([1]); // sync queue in tests

        $run->refresh();
        $this->assertSame('finished', $run->status);
        $this->assertSame(1, $run->species_total);
        $this->assertSame(3, $run->fetched);
        $this->assertSame(3, $run->inserted);
        $this->assertSame(2, $run->with_coordinates);
        $this->assertSame(3, GbifOccurrence::count());
        $o = GbifOccurrence::find(5001234567);
        $this->assertEqualsWithDelta(45.2871, $o->latitude, 1e-6);
        $this->assertSame(1, (int) $o->idsp);
        $this->assertSame('pending', $o->state);
        Http::assertSent(fn (Request $r) => $r['taxon_key'] === 7792353 && $r['offset'] === 0 && $r['limit'] === 300);
        Http::assertSent(fn (Request $r) => $r['offset'] === 2);

        // Second run: one changed, two unchanged; still 3 rows.
        $run2 = app(OccurrenceImporter::class)->start([1])->refresh();
        $this->assertSame([0, 1, 2], [$run2->inserted, $run2->updated, $run2->unchanged]);
        $this->assertSame(3, GbifOccurrence::count());
        $this->assertSame('Učka, Poklon', GbifOccurrence::find(5001234567)->locality);

        $this->assertEquals($legacyBefore, DB::connection('chrysis')->table('occurrences')->get());
    }

    public function test_limit_option_caps_records(): void
    {
        $this->fakeGbif([['count' => 50, 'endOfRecords' => false, 'results' => [$this->record(11), $this->record(12)]]]);

        $run = app(OccurrenceImporter::class)->start([1], 2)->refresh();

        $this->assertSame(2, $run->fetched);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r['limit'] === 2);
    }

    public function test_only_matched_species_are_imported(): void
    {
        Queue::fake();

        $run = app(OccurrenceImporter::class)->start([1, 2, 3]); // 2 has gbif_id=0, 3 has no match row

        $this->assertSame(1, $run->species_total);
        Queue::assertPushed(ImportSpeciesOccurrences::class, 1);
        Queue::assertPushed(ImportSpeciesOccurrences::class, fn ($j) => $j->idsp === 1 && $j->taxonKey === 7792353);
    }

    public function test_gbif_failure_is_recorded_on_the_run(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response('down', 503)]);

        $run = app(OccurrenceImporter::class)->start([1])->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('idsp 1', $run->errors[0]);
        $this->assertSame(0, GbifOccurrence::count());
    }

    public function test_artisan_command(): void
    {
        $this->fakeGbif([['count' => 1, 'endOfRecords' => true, 'results' => [$this->record(77)]]]);

        $this->artisan('gbif:import', ['--species' => [1], '--limit' => 10])
            ->expectsOutputToContain('queued for 1 species')
            ->expectsOutputToContain('inserted 1')
            ->assertSuccessful();

        $this->artisan('gbif:import', ['--species' => [2]])->expectsOutputToContain('No matched species')->assertFailed();
        $this->assertSame(2, GbifImportRun::count()); // one real run, one empty
    }

    public function test_ui_import_requires_update_gbif(): void
    {
        Queue::fake();
        $this->actingAs(Account::find(1));

        Livewire::test(Show::class, ['idsp' => 1])->call('startImport')->assertForbidden();
        $this->get('/species/1')->assertSee('data-import-button-disabled', false);

        AccountPermission::create(['account_id' => 1, 'update_gbif' => true]);
        $this->get('/species/1')->assertSee('data-import-button', false);
        Livewire::test(Show::class, ['idsp' => 1])->call('startImport')->assertDispatched('toast', type: 'success');
        Queue::assertPushed(ImportSpeciesOccurrences::class, 1);
        $this->assertDatabaseHas('gbif_audit_log', ['action' => 'gbif_import_started', 'target' => 'idsp:1']);

        Livewire::test(Show::class, ['idsp' => 2])->call('startImport')->assertDispatched('toast', type: 'error');
    }

    public function test_detail_page_shows_map_points_and_coordinates_column(): void
    {
        $this->fakeGbif([['count' => 2, 'endOfRecords' => true, 'results' => [$this->record(5001234567), $this->record(5001234568, ['decimalLatitude' => null, 'decimalLongitude' => null, 'locality' => 'Nowhere'])]]]);
        app(OccurrenceImporter::class)->start([1]);

        $this->actingAs(Account::find(1))->get('/species/1')->assertOk()
            ->assertSee('data-map-points="1"', false)
            ->assertSee('45.28710, 14.20340 ±250 m')
            ->assertSee('Nowhere')
            ->assertSee('vendor/leaflet/leaflet.js')
            ->assertSee('1 with coordinates');

        Livewire::actingAs(Account::find(1))->test(Show::class, ['idsp' => 1])->set('filters.coords', '1')
            ->assertSee('Učka')->assertDontSee('Nowhere');
    }
}
