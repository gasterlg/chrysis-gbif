<?php

namespace Tests\Feature;

use App\Gbif\AccountManager;
use App\Gbif\DatasetFetcher;
use App\Livewire\Admin\Users;
use App\Livewire\Data\Datasets;
use App\Livewire\Data\Exports;
use App\Livewire\Review\History;
use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\GbifImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): Account
    {
        return Account::find(2);
    }

    private function occ(int $gbifId, string $state, string $dataset = '7e380070-f762-11e1-a439-00145eb45e9a', int $idsp = 1): void
    {
        DB::connection('chrysis')->table('gbif_occurrences')->insert([
            'gbif_id' => $gbifId, 'idsp' => $idsp, 'taxon_key' => 1, 'scientific_name' => 'X y', 'state' => $state,
            'dataset_key' => $dataset, 'latitude' => 45.0, 'longitude' => 9.0, 'country_code' => 'IT', 'media_count' => 1,
            'content_hash' => sha1((string) $gbifId),
        ]);
    }

    public function test_users_screen_is_admin_only(): void
    {
        $this->actingAs(Account::find(1))->get('/users')->assertForbidden();
        $this->actingAs($this->admin())->get('/users')->assertOk()->assertSee('puzzo');
    }

    public function test_admin_creates_edits_and_resets_password(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(Users::class)->call('create')
            ->set('form.username', 'newbie')->set('form.email', 'new@example.test')->set('form.role', 'SuperUser')
            ->set('form.password', 'long-enough-pw')->set('form.password_confirmation', 'long-enough-pw')
            ->call('save')->assertHasNoErrors();
        $new = Account::where('username', 'newbie')->firstOrFail();
        $this->assertStringStartsWith('$2y$', $new->password);
        $this->assertSame(0, (int) DB::connection('chrysis')->table('accounts_api')->where('id', $new->id)->value('approve'));

        Livewire::test(Users::class)->call('edit', $new->id)->set('form.role', 'Admin')->call('save')->assertHasNoErrors();
        $this->assertSame('Admin', $new->fresh()->role);

        Livewire::test(Users::class)->call('askPassword', $new->id)
            ->set('newPassword', 'another-long-pw')->set('newPassword_confirmation', 'another-long-pw')->call('resetPassword')->assertHasNoErrors();
        $this->assertTrue(Hash::check('another-long-pw', $new->fresh()->password));

        foreach (['user_created', 'user_updated', 'user_password_reset'] as $a) {
            $this->assertTrue(AuditLog::where('action', $a)->where('target', "account:{$new->id}")->exists(), $a);
        }
        $this->assertFalse(str_contains(AuditLog::pluck('meta')->toJson(), 'another-long-pw'));
    }

    public function test_duplicate_username_and_short_password_rejected(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(Users::class)->call('create')
            ->set('form.username', 'puzzo')->set('form.email', 'x@example.test')->set('form.password', 'short')->set('form.password_confirmation', 'short')
            ->call('save')->assertHasErrors(['form.username', 'form.password']);
    }

    public function test_admin_cannot_demote_or_disable_self(): void
    {
        $m = app(AccountManager::class);
        $this->expectExceptionMessage('You cannot remove your own Admin role.');
        $m->update($this->admin(), ['role' => 'SuperUser'], $this->admin());
    }

    public function test_admin_cannot_disable_self(): void
    {
        $this->expectExceptionMessage('You cannot disable your own account.');
        app(AccountManager::class)->setDisabled($this->admin(), true, $this->admin());
    }

    public function test_disabled_user_cannot_login_and_is_logged_out(): void
    {
        app(AccountManager::class)->setDisabled(Account::find(1), true, $this->admin());
        $this->assertTrue(AccountPermission::where('account_id', 1)->value('disabled'));
        $this->assertTrue(AuditLog::where('action', 'user_disabled')->exists());

        $this->actingAs(Account::find(1))->get('/')->assertRedirect('/login');
        $this->assertGuest();

        app(AccountManager::class)->setDisabled(Account::find(1), false, $this->admin());
        $this->actingAs(Account::find(1))->get('/')->assertOk();
    }

    public function test_toggle_update_gbif(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(Users::class)->call('toggleUpdateGbif', 3);
        $this->assertTrue(Account::find(3)->can('update-gbif'));
        $this->assertTrue(AuditLog::where('action', 'user_update_gbif_granted')->exists());
    }

    public function test_history_filters_and_csv(): void
    {
        AccountPermission::updateOrCreate(['account_id' => 1], ['update_gbif' => true]);
        AuditLog::record('match_accepted', 1, 'puzzo', 'idsp:1', ['key' => 5]);
        AuditLog::record('login', 1, 'puzzo');
        $this->actingAs(Account::find(1));
        Livewire::test(History::class)->set('filters.category', 'match')
            ->assertSee('match accepted')->assertDontSee('>login<', false);
        $res = Livewire::test(History::class)->call('exportCsv');
        $res->assertFileDownloaded();
    }

    public function test_history_logs_cli_imports(): void
    {
        $run = GbifImportRun::create(['type' => 'occurrences', 'status' => 'running', 'species_total' => 1]);
        $run->addSpeciesResult(['fetched' => 3, 'inserted' => 3, 'updated' => 0, 'unchanged' => 0, 'with_coordinates' => 3]);
        $this->assertTrue(AuditLog::where('action', 'gbif_import_finished')->where('target', "run:{$run->id}")->exists());
        $this->artisan('gbif:backfill-history')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'gbif_import_finished')->count());
    }

    public function test_export_zip_contains_only_approved_with_citation(): void
    {
        $this->occ(101, 'approved');
        $this->occ(102, 'pending');
        DB::connection('chrysis')->table('gbif_datasets')->insert(['dataset_key' => '7e380070-f762-11e1-a439-00145eb45e9a', 'title' => 'NHM specimens', 'doi' => '10.5519/qd.6xds2wew']);
        $this->actingAs(Account::find(1));
        $resp = app(\App\Gbif\OccurrenceExporter::class)->download(null);
        ob_start();
        $resp->sendContent();
        $bytes = ob_get_clean();
        $tmp = tempnam(sys_get_temp_dir(), 'z');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $csv = $zip->getFromName('occurrences.csv');
        $this->assertStringContainsString('101', $csv);
        $this->assertStringNotContainsString('102', $csv);
        $this->assertStringContainsString('10.5519/qd.6xds2wew', $zip->getFromName('CITATION.txt'));
        $this->assertStringContainsString('NHM specimens', $zip->getFromName('datasets.csv'));
        unlink($tmp);

        Livewire::test(Exports::class)->call('export')->assertFileDownloaded();
        $this->assertTrue(AuditLog::where('action', 'export_occurrences')->exists());
    }

    public function test_download_api_disabled_without_credentials(): void
    {
        config(['gbif.download.user' => null, 'gbif.download.password' => null]);
        $this->artisan('gbif:download')->assertFailed();
        $this->actingAs(Account::find(1))->get('/exports')->assertOk()->assertSee('Not configured');
    }

    public function test_download_api_request_and_poll(): void
    {
        config(['gbif.download.user' => 'u', 'gbif.download.password' => 'p']);
        Http::fake([
            '*/v1/occurrence/download/request' => Http::response('0001-2026', 201),
            '*/v1/occurrence/download/0001-2026' => Http::response(['status' => 'SUCCEEDED', 'doi' => '10.15468/dl.abc', 'downloadLink' => 'https://x/0001.zip', 'totalRecords' => 9]),
        ]);
        $this->artisan('gbif:download')->assertSuccessful();
        $run = GbifImportRun::where('type', 'download')->firstOrFail();
        $this->artisan('gbif:download', ['--status' => $run->id])->assertSuccessful();
        $this->assertSame('10.15468/dl.abc', $run->fresh()->download_doi);
        $this->assertSame('finished', $run->fresh()->status);
    }

    public function test_dataset_fetch_and_view(): void
    {
        $this->occ(201, 'approved', '2356f899-6244-4bbb-aa33-50e29132e86a');
        Http::fake(['*/v1/dataset/*' => fn ($req) => Http::response(['title' => 'DS '.basename($req->url()), 'doi' => '10.1/x', 'license' => 'http://creativecommons.org/publicdomain/zero/1.0/legalcode', 'citation' => ['text' => 'Cite me']])]);
        $this->artisan('gbif:datasets')->assertSuccessful();
        $this->assertSame(count(DatasetFetcher::HIGHLIGHT), DB::connection('chrysis')->table('gbif_datasets')->count());
        $this->actingAs(Account::find(1));
        Livewire::test(Datasets::class)->assertSee('Lund (inventory)')->assertSee('DS 2356f899-6244-4bbb-aa33-50e29132e86a');
    }

    public function test_idle_timeout_is_two_hours(): void
    {
        $this->assertSame(120, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }
}
