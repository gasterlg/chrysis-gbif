<?php

namespace Tests\Feature;

use App\Livewire\Species\GbifPanel;
use App\Livewire\Species\Index;
use App\Livewire\Species\Show;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SpeciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Account::find(1));
    }

    public function test_species_list_shows_all_species_with_status(): void
    {
        $this->get('/species')->assertOk()
            ->assertSee('Cleptes aerosus')
            ->assertSee('Chrysis ignita')
            ->assertSee('Chrysis mediadentata ssp1')
            ->assertSee('data-status="matched"', false)
            ->assertSee('data-status="failed"', false)
            ->assertSee('data-status="none"', false)
            ->assertSee('3 records');
    }

    public function test_failed_filter_flags_gbif_id_zero(): void
    {
        $c = Livewire::test(Index::class)->set('filters.status', 'failed');
        $this->assertSame([2], $c->instance()->filteredQuery()->pluck('IDSP')->map(fn ($v) => (int) $v)->all());
        $c->assertSee('Chrysis ignita')->assertDontSee('Cleptes aerosus');

        Livewire::test(Index::class)->set('filters.status', 'matched')->assertSee('Cleptes aerosus')->assertDontSee('Chrysis ignita');
        Livewire::test(Index::class)->set('filters.status', 'none')->assertSee('mediadentata')->assertDontSee('Cleptes aerosus');
    }

    public function test_invalid_filter_value_is_ignored(): void
    {
        $c = Livewire::test(Index::class)->set('filters.status', 'bogus');
        $this->assertSame(3, $c->instance()->filteredQuery()->count());
    }

    public function test_search_matches_genus_and_epithet_words(): void
    {
        $c = Livewire::test(Index::class)->set('search', 'chrysis igni');
        $this->assertSame([2], $c->instance()->filteredQuery()->pluck('IDSP')->map(fn ($v) => (int) $v)->all());
    }

    public function test_sorting_toggles_direction(): void
    {
        $c = Livewire::test(Index::class)->call('sortBy', 'IDSP');
        $this->assertSame([1, 2, 3], $c->instance()->filteredQuery()->pluck('IDSP')->map(fn ($v) => (int) $v)->all());
        $c->call('sortBy', 'IDSP')->assertSet('sortDirection', 'desc');
        $this->assertSame([3, 2, 1], $c->instance()->filteredQuery()->pluck('IDSP')->map(fn ($v) => (int) $v)->all());

        // Unknown / non-sortable columns are ignored.
        $c->call('sortBy', 'password')->assertSet('sortField', 'IDSP');
    }

    public function test_pagination_and_per_page(): void
    {
        Livewire::test(Index::class)->set('perPage', 10)->assertSet('perPage', 10)
            ->set('perPage', 7)->assertSet('perPage', 25);
    }

    public function test_bulk_export_requires_selection_and_downloads_csv(): void
    {
        Livewire::test(Index::class)->call('runBulkAction', 'export-selected')
            ->assertDispatched('toast', type: 'warning')
            ->assertNoFileDownloaded();

        Livewire::test(Index::class)->call('togglePage', [1, 2, 3])->assertSet('selected', ['1', '2', '3'])
            ->call('togglePage', [1, 2, 3])->assertSet('selected', [])
            ->set('selected', ['1'])
            ->call('runBulkAction', 'export-selected')
            ->assertDispatched('toast', type: 'success')
            ->assertFileDownloaded();
    }

    public function test_unknown_bulk_action_is_forbidden(): void
    {
        Livewire::test(Index::class)->set('selected', ['1'])->call('runBulkAction', 'delete-all')->assertForbidden();
    }

    public function test_csv_export_streams_filtered_rows(): void
    {
        $c = Livewire::test(Index::class)->set('filters.status', 'failed');
        $response = $c->instance()->exportCsv();
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Species,Author,Subfamily,IDSP', $csv);
        $this->assertStringContainsString('Chrysis ignita', $csv);
        $this->assertStringNotContainsString('Cleptes', $csv);
    }

    public function test_species_detail_shows_local_occurrences_and_lazy_gbif_panel(): void
    {
        $this->get('/species/1')->assertOk()
            ->assertSee('Cleptes aerosus')
            ->assertSee('Croatia')->assertSee('Budapest')
            ->assertDontSee('Roma')
            ->assertSee('2 records');

        $this->get('/species/999')->assertNotFound();
    }

    public function test_detail_filters_occurrences(): void
    {
        Livewire::test(Show::class, ['idsp' => 1])->set('filters.country', 'Hungary')
            ->assertSee('Budapest')->assertDontSee('Učka');
    }

    public function test_failed_species_shows_flag_and_no_occurrences(): void
    {
        $this->get('/species/2')->assertOk()->assertSee('gbif_id = 0')->assertSee('No records found.');
    }

    public function test_gbif_panel_shows_live_match_and_agreement(): void
    {
        Http::fake([
            'api.gbif.org/v2/species/match*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/gbif/match_cleptes_aerosus.json')), true)),
            'api.gbif.org/v1/occurrence/search*' => Http::response(['count' => 33, 'results' => [], 'endOfRecords' => false]),
        ]);

        Livewire::withoutLazyLoading()->test(GbifPanel::class, ['name' => 'Cleptes aerosus', 'author' => 'Förster, 1853', 'localKey' => 7792353])
            ->assertSee('7792353')->assertSee('EXACT')->assertSee('agrees')->assertSee('33');

        Livewire::withoutLazyLoading()->test(GbifPanel::class, ['name' => 'Cleptes aerosus', 'author' => 'Förster, 1853', 'localKey' => 123])
            ->assertSee('differs: local 123');
    }

    public function test_gbif_panel_handles_outage(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response('down', 503)]);

        Livewire::withoutLazyLoading()->test(GbifPanel::class, ['name' => 'Chrysis ignita', 'localKey' => null])
            ->assertSee('GBIF is not reachable');
    }
}
