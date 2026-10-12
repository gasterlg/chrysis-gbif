<?php

namespace Tests\Feature;

use App\Gbif\MatchApplier;
use App\Gbif\MatchProposer;
use App\Livewire\Review\Changes;
use App\Livewire\Review\Matches;
use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\GbifChange;
use App\Models\GbifMatchProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MatchReviewTest extends TestCase
{
    use RefreshDatabase;

    private function usage(int $key, string $canonical, string $rank = 'SPECIES', string $status = 'ACCEPTED'): array
    {
        return ['key' => (string) $key, 'name' => $canonical.' Author, 1900', 'canonicalName' => $canonical, 'rank' => $rank, 'status' => $status];
    }

    /** GBIF fake keyed by the scientificName query. */
    private function fakeGbif(): void
    {
        Http::fake(function (Request $r) {
            if (str_contains($r->url(), '/v1/species/')) {
                $key = (int) basename(parse_url($r->url(), PHP_URL_PATH));

                return match ($key) {
                    111 => Http::response(['key' => 111, 'scientificName' => 'Chrysis ignita Linnaeus, 1761', 'canonicalName' => 'Chrysis ignita', 'rank' => 'SPECIES', 'taxonomicStatus' => 'ACCEPTED']),
                    10400727 => Http::response(['key' => 10400727, 'canonicalName' => 'Chrysis ignita B', 'rank' => 'SPECIES', 'taxonomicStatus' => 'ACCEPTED']),
                    222 => Http::response(['key' => 222, 'scientificName' => 'Chrysis oldname', 'canonicalName' => 'Chrysis oldname', 'rank' => 'SPECIES', 'taxonomicStatus' => 'SYNONYM', 'acceptedKey' => 111]),
                    default => Http::response(['message' => 'not found'], 404),
                };
            }
            parse_str(parse_url($r->url(), PHP_URL_QUERY), $q);

            return match ($q['scientificName']) {
                'Cleptes aerosus' => Http::response(['usage' => $this->usage(7792353, 'Cleptes aerosus'), 'diagnostics' => ['matchType' => 'EXACT', 'confidence' => 99]]),
                'Chrysis ignita' => Http::response(['usage' => $this->usage(5760254, 'Chrysis ignita'), 'diagnostics' => ['matchType' => 'EXACT', 'confidence' => 98,
                    'alternatives' => [['usage' => $this->usage(10400727, 'Chrysis ignita B'), 'diagnostics' => ['matchType' => 'EXACT', 'confidence' => 90]]]]]),
                'Chrysis mediadentata ssp1' => Http::response(['usage' => $this->usage(9001, 'Chrysis oldname', 'SUBSPECIES', 'SYNONYM'),
                    'acceptedUsage' => ['key' => '9002', 'name' => 'Chrysis newname Author', 'canonicalName' => 'Chrysis newname'], 'diagnostics' => ['matchType' => 'FUZZY', 'confidence' => 92]]),
                default => Http::response(['usage' => $this->usage(4679280, 'Chrysis', 'GENUS'), 'diagnostics' => ['matchType' => 'HIGHERRANK', 'confidence' => 100]]),
            };
        });
    }

    private function editor(): Account
    {
        AccountPermission::create(['account_id' => 1, 'update_gbif' => true]);

        return Account::find(1);
    }

    public function test_command_proposes_failed_first_and_never_writes_match(): void
    {
        $this->fakeGbif();
        $before = DB::connection('chrysis')->table('match')->get();

        $this->assertSame([2, 3, 1], app(MatchProposer::class)->orderedSpecies());
        $this->artisan('gbif:match', ['--sync' => true])->assertSuccessful();

        $this->assertEquals($before, DB::connection('chrysis')->table('match')->get());
        $p = GbifMatchProposal::all()->keyBy('idsp');
        $this->assertSame('same', $p[1]->status);                                   // already 7792353
        $this->assertSame('pending', $p[2]->status);
        $this->assertSame(5760254, (int) $p[2]->proposed_key);
        $this->assertSame(0, (int) $p[2]->current_gbif_id);
        $this->assertCount(1, $p[2]->alternatives);
        $this->assertSame(9002, (int) $p[3]->proposed_key);                         // synonym → accepted key
        $this->assertNull($p[3]->current_gbif_id);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'verbose=true') && str_contains($r->url(), 'family=Chrysididae'));
    }

    public function test_higher_rank_match_proposes_nothing(): void
    {
        $this->fakeGbif();
        DB::connection('chrysis')->table('_specie')->insert(['IDSP' => 4, 'SUBF' => 'X', 'TRIBE' => '0', 'GEN' => 'Chrysellampus', 'SP' => 'pici', 'SSP' => '0', 'AUTH' => '0']);

        $p = app(MatchProposer::class)->propose(4);

        $this->assertSame('HIGHERRANK', $p->match_type);
        $this->assertNull($p->proposed_key);
        $this->assertSame('pending', $p->status);
    }

    public function test_accept_updates_match_with_snapshot_and_rollback_restores(): void
    {
        $this->fakeGbif();
        $user = $this->editor();
        $p = app(MatchProposer::class)->propose(2);

        $change = app(MatchApplier::class)->accept($p, $user);

        $row = DB::connection('chrysis')->table('match')->where('idsp', 2)->first();
        $this->assertSame(5760254, (int) $row->gbif_id);
        $this->assertSame('Chrysis ignita', $row->gbif_species);
        $this->assertSame(0, (int) $change->before['gbif_id']);
        $this->assertSame(5760254, (int) $change->after['gbif_id']);
        $this->assertSame('accepted', $p->fresh()->status);
        $this->assertDatabaseHas('gbif_audit_log', ['action' => 'match_accepted', 'target' => 'idsp:2']);

        app(MatchApplier::class)->rollback($change, $user);

        $this->assertSame(0, (int) DB::connection('chrysis')->table('match')->where('idsp', 2)->value('gbif_id'));
        $this->assertNotNull($change->fresh()->rolled_back_at);
        $this->assertSame('rolled_back', $p->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        app(MatchApplier::class)->rollback($change->fresh(), $user);
    }

    public function test_accept_inserts_missing_match_row_and_rollback_deletes_it(): void
    {
        $this->fakeGbif();
        $user = $this->editor();
        $change = app(MatchApplier::class)->accept(app(MatchProposer::class)->propose(3), $user);

        $this->assertNull($change->before);
        $row = DB::connection('chrysis')->table('match')->where('idsp', 3)->first();
        $this->assertSame(['Chrysis', 'mediadentata', 'ssp1', 9002], [$row->gen, $row->sp, $row->ssp, (int) $row->gbif_id]);

        app(MatchApplier::class)->rollback($change, $user);
        $this->assertNull(DB::connection('chrysis')->table('match')->where('idsp', 3)->first());
    }

    public function test_rollback_refuses_when_row_changed_later(): void
    {
        $this->fakeGbif();
        $user = $this->editor();
        $change = app(MatchApplier::class)->accept(app(MatchProposer::class)->propose(2), $user);
        DB::connection('chrysis')->table('match')->where('idsp', 2)->update(['gbif_id' => 42]);

        $this->expectExceptionMessage('changed after');
        app(MatchApplier::class)->rollback($change, $user);
    }

    public function test_manual_key_resolves_synonyms_and_validates(): void
    {
        $this->fakeGbif();
        $this->editor();
        app(MatchProposer::class)->propose(2);
        $p = GbifMatchProposal::where('idsp', 2)->first();

        $c = Livewire::actingAs(Account::find(1))->test(Matches::class)->call('review', $p->id)->assertDispatched('open-modal');
        $c->set('manualKey', 'abc')->call('acceptManual')->assertHasErrors('manualKey');
        $c->set('manualKey', '999')->call('acceptManual')->assertHasErrors('manualKey')->assertSee('GBIF has no taxon with key 999');
        $c->set('manualKey', '222')->call('acceptManual')->assertHasNoErrors()->assertDispatched('toast', type: 'success');

        $this->assertSame(111, (int) DB::connection('chrysis')->table('match')->where('idsp', 2)->value('gbif_id'));
        $this->assertSame('manual key 222', GbifChange::latest('id')->first()->reason);
    }

    public function test_pick_alternative(): void
    {
        $this->fakeGbif();
        $this->editor();
        $p = app(MatchProposer::class)->propose(2);

        Livewire::actingAs(Account::find(1))->test(Matches::class)->call('acceptAlternative', $p->id, 10400727)->assertDispatched('toast', type: 'success');
        $this->assertSame(10400727, (int) DB::connection('chrysis')->table('match')->where('idsp', 2)->value('gbif_id'));
    }

    public function test_bulk_accept_only_exact_95_and_reject(): void
    {
        $this->fakeGbif();
        $this->editor();
        $this->artisan('gbif:match', ['--sync' => true]);
        $ids = GbifMatchProposal::pluck('id', 'idsp');

        Livewire::actingAs(Account::find(1))->test(Matches::class)
            ->set('selected', [(string) $ids[2], (string) $ids[3]])
            ->call('runBulkAction', 'accept-exact')
            ->assertDispatched('toast', message: '1 accepted, 1 skipped (not exact ≥95 / not pending).');

        $this->assertSame('accepted', GbifMatchProposal::find($ids[2])->status);
        $this->assertSame('pending', GbifMatchProposal::find($ids[3])->status);

        Livewire::actingAs(Account::find(1))->test(Matches::class)->set('selected', [(string) $ids[3]])->call('runBulkAction', 'reject');
        $this->assertSame('rejected', GbifMatchProposal::find($ids[3])->status);
    }

    public function test_accept_all_exact_button(): void
    {
        $this->fakeGbif();
        $this->editor();
        $this->artisan('gbif:match', ['--sync' => true]);

        Livewire::actingAs(Account::find(1))->test(Matches::class)->assertSee('Accept all exact ≥95 (1)')->call('acceptAllExact');
        $this->assertSame(5760254, (int) DB::connection('chrysis')->table('match')->where('idsp', 2)->value('gbif_id'));
    }

    public function test_review_screens_require_update_gbif(): void
    {
        $this->actingAs(Account::find(1));
        foreach (['/review/matches', '/review/occurrences', '/review/changes'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/')->assertDontSee('Match review');

        AccountPermission::create(['account_id' => 1, 'update_gbif' => true]);
        foreach (['/review/matches', '/review/occurrences', '/review/changes'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('Match review');
    }

    public function test_changes_screen_rolls_back(): void
    {
        $this->fakeGbif();
        $user = $this->editor();
        $change = app(MatchApplier::class)->accept(app(MatchProposer::class)->propose(2), $user);

        Livewire::actingAs($user)->test(Changes::class)->assertSee('5760254')->call('rollback', $change->id)->assertDispatched('toast', type: 'success');
        $this->assertSame(0, (int) DB::connection('chrysis')->table('match')->where('idsp', 2)->value('gbif_id'));
        $this->assertDatabaseHas('gbif_audit_log', ['action' => 'match_rolled_back']);
    }
}
