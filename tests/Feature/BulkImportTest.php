<?php

namespace Tests\Feature;

use App\Gbif\QueueRunner;
use App\Jobs\ImportSpeciesOccurrences;
use App\Livewire\Species\Index;
use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\GbifImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    private function curator(): Account
    {
        AccountPermission::updateOrCreate(['account_id' => 1], ['update_gbif' => true]);

        return Account::find(1);
    }

    public function test_bulk_import_queues_matched_and_reports_skipped(): void
    {
        Queue::fake();
        $db = DB::connection('chrysis');
        $matched = $db->table('match')->where('gbif_id', '>', 0)->limit(1)->pluck('idsp')->all();
        $unmatched = $db->table('match')->where('gbif_id', 0)->value('idsp');
        $this->assertCount(1, $matched);
        $this->assertNotNull($unmatched);

        $this->actingAs($this->curator());
        Livewire::test(Index::class)->set('selected', array_map('strval', [...$matched, $unmatched]))
            ->call('runBulkAction', 'import')
            ->assertDispatched('toast', fn ($n, $p) => $p['type'] === 'success' && str_contains($p['message'], 'for 1 species') && str_contains($p['message'], '1 skipped'))
            ->assertSet('selected', []);

        Queue::assertPushed(ImportSpeciesOccurrences::class, 1);
        $this->assertSame(1, GbifImportRun::latest('id')->first()->species_total);
        $this->assertTrue(AuditLog::where('action', 'gbif_import_started')->exists());
    }

    public function test_bulk_import_with_only_unmatched_does_nothing(): void
    {
        Queue::fake();
        $unmatched = DB::connection('chrysis')->table('match')->where('gbif_id', 0)->value('idsp');
        $this->actingAs($this->curator());
        Livewire::test(Index::class)->set('selected', [(string) $unmatched])->call('runBulkAction', 'import')
            ->assertDispatched('toast', fn ($n, $p) => $p['type'] === 'warning');
        Queue::assertNothingPushed();
        $this->assertSame(0, GbifImportRun::count());
    }

    public function test_bulk_import_requires_update_gbif_and_shows_disabled_button(): void
    {
        $this->actingAs(Account::find(3));
        Livewire::test(Index::class)->set('selected', ['1'])->call('runBulkAction', 'import')->assertForbidden();
        $this->get('/species')->assertSee('Needs the Update GBIF permission');
        $this->get('/species/1')->assertSee('data-import-button-disabled', false)->assertSee('Needs the Update GBIF permission');
    }

    public function test_curator_sees_enabled_buttons_with_confirm(): void
    {
        $this->actingAs($this->curator());
        $this->get('/species')->assertSee('data-bulk="import"', false)->assertDontSee('Needs the Update GBIF permission');
        $this->get('/species/1')->assertSee('data-import-button', false)->assertDontSee('data-import-button-disabled', false);
    }

    public function test_queue_runner_skips_on_sync_queue_and_when_disabled(): void
    {
        $this->assertFalse(app(QueueRunner::class)->kick()); // phpunit uses the sync queue
        config(['queue.default' => 'database', 'gbif.worker.auto' => false]);
        $this->assertFalse(app(QueueRunner::class)->kick());
    }

    public function test_guides_say_where_the_import_button_is(): void
    {
        $this->actingAs($this->curator());
        foreach (['/', '/species', '/species/1'] as $url) {
            $this->get($url)->assertSee('Where is the Import button?')->assertSee('Update GBIF');
        }
    }
}
