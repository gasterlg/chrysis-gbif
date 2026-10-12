<?php

namespace Tests\Feature;

use App\Gbif\OccurrenceImporter;
use App\Gbif\OccurrenceMapper;
use App\Gbif\OutlierFlagger;
use App\Livewire\Review\Occurrences;
use App\Livewire\Species\Show;
use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\GbifOccurrence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OccurrenceReviewTest extends TestCase
{
    use RefreshDatabase;

    private function seedOcc(int $id, float $lat, float $lon, string $cc = 'IT', array $extra = []): void
    {
        GbifOccurrence::create(OccurrenceMapper::toRow($extra + ['gbifID' => $id, 'taxonKey' => 7792353, 'speciesKey' => 7792353,
            'decimalLatitude' => $lat, 'decimalLongitude' => $lon, 'countryCode' => $cc, 'country' => $cc, 'locality' => "loc{$id}"]) + ['idsp' => 1]);
    }

    public function test_outlier_flags_far_points_and_rare_countries(): void
    {
        // 20 records around Italy, one in California, one in Albania (rare country, but near).
        for ($i = 1; $i <= 20; $i++) {
            $this->seedOcc($i, 43 + $i * 0.1, 11 + $i * 0.1);
        }
        $this->seedOcc(100, 37.5, -120.0, 'US');
        $this->seedOcc(101, 41.3, 19.8, 'AL');
        $this->seedOcc(102, 44.0, 12.0, 'IT');

        $n = app(OutlierFlagger::class)->flagSpecies(1);

        $this->assertSame(2, $n);
        $this->assertSame(['far_from_range', 'rare_country'], GbifOccurrence::find(100)->flags);
        $this->assertSame(['rare_country'], GbifOccurrence::find(101)->flags);
        $this->assertNull(GbifOccurrence::find(102)->flags);
        $this->artisan('gbif:flag-outliers')->expectsOutputToContain('2 records flagged')->assertSuccessful();
    }

    public function test_changed_record_returns_to_pending_and_new_ones_are_marked_new(): void
    {
        $rec = fn (string $loc) => ['key' => 5, 'gbifID' => '5', 'taxonKey' => 7792353, 'locality' => $loc];
        Http::fake(['api.gbif.org/*' => Http::sequence()
            ->push(['count' => 1, 'endOfRecords' => true, 'results' => [$rec('A')]])
            ->push(['count' => 1, 'endOfRecords' => true, 'results' => [$rec('B')]])]);

        app(OccurrenceImporter::class)->start([1]);
        $this->assertSame('new', GbifOccurrence::find(5)->change_type);
        GbifOccurrence::whereKey(5)->update(['state' => 'approved']);

        app(OccurrenceImporter::class)->start([1]);
        $o = GbifOccurrence::find(5);
        $this->assertSame(['changed', 'pending', 'B'], [$o->change_type, $o->state, $o->locality]);
    }

    public function test_approve_and_reject_single_bulk_and_unflagged(): void
    {
        AccountPermission::create(['account_id' => 1, 'update_gbif' => true]);
        $user = Account::find(1);
        foreach ([1, 2, 3, 4] as $i) {
            $this->seedOcc($i, 44, 11);
        }
        GbifOccurrence::whereKey(4)->update(['flags' => json_encode(['far_from_range'])]);

        $c = Livewire::actingAs($user)->test(Occurrences::class)->assertSee('pending: 4');
        $c->call('decideOne', 1, 'approved')->assertDispatched('toast', message: '1 record(s) approved.');
        $c->set('selected', ['2'])->call('runBulkAction', 'reject');
        $this->assertSame(['approved', 'rejected'], [GbifOccurrence::find(1)->state, GbifOccurrence::find(2)->state]);
        $this->assertSame($user->id, (int) GbifOccurrence::find(1)->reviewed_by);

        $c->set('filters.idsp', '1')->assertSee('Approve all unflagged')->call('approveUnflagged');
        $this->assertSame('approved', GbifOccurrence::find(3)->state);
        $this->assertSame('pending', GbifOccurrence::find(4)->state);       // flagged one stays for review
        $this->assertDatabaseHas('gbif_audit_log', ['action' => 'occurrences_approved', 'target' => 'idsp:1']);

        $c->set('filters.flagged', '1')->assertSee('far from range');
    }

    public function test_invalid_decision_is_refused(): void
    {
        AccountPermission::create(['account_id' => 1, 'update_gbif' => true]);
        $this->seedOcc(1, 44, 11);
        Livewire::actingAs(Account::find(1))->test(Occurrences::class)->call('decideOne', 1, 'deleted')->assertStatus(422);
    }

    public function test_last_import_shows_cli_runs(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response(['count' => 1, 'endOfRecords' => true, 'results' => [['key' => 9, 'gbifID' => '9', 'taxonKey' => 7792353]]])]);
        // CLI-style run covering several species; only idsp 1 is matched in the fixture, so add idsp 3 to scope via a direct run.
        $run = app(OccurrenceImporter::class)->start([]);  // all matched species (idsp 1)
        $this->assertSame([1], $run->scope['idsp']);
        \App\Models\GbifImportRun::create(['scope' => ['idsp' => [5, 10]], 'status' => 'finished', 'species_total' => 2]);

        Livewire::actingAs(Account::find(1))->test(Show::class, ['idsp' => 1])
            ->assertSee('#'.$run->id)->assertSee('data-run-status="finished"', false);
    }
}
