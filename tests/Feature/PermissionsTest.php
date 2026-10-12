<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gate_follows_accounts_api_role(): void
    {
        $this->assertTrue(Gate::forUser(Account::find(2))->allows('admin'));
        $this->assertFalse(Gate::forUser(Account::find(1))->allows('admin'));
    }

    public function test_update_gbif_flag_lives_in_app_db_and_defaults_to_false(): void
    {
        $user = Account::find(1);
        $this->assertFalse(Gate::forUser($user)->allows('update-gbif'));

        AccountPermission::create(['account_id' => 1, 'update_gbif' => true]);
        $this->assertTrue(Gate::forUser($user)->allows('update-gbif'));
        $this->assertFalse(Gate::forUser(Account::find(2))->allows('update-gbif'));
    }

    public function test_audit_screen_is_admin_only_and_menu_item_hidden(): void
    {
        $this->actingAs(Account::find(1))->get('/audit')->assertForbidden();
        $this->actingAs(Account::find(1))->get('/')->assertOk()->assertDontSee('Login audit');

        $this->actingAs(Account::find(2))->get('/audit')->assertOk()->assertSee('Login audit');
        $this->actingAs(Account::find(2))->get('/')->assertSee('Login audit');
    }

    public function test_user_menu_shows_update_gbif_badge_only_when_granted(): void
    {
        $this->actingAs(Account::find(1))->get('/')->assertDontSee('update_gbif');
        AccountPermission::create(['account_id' => 1, 'update_gbif' => true]);
        $this->actingAs(Account::find(1))->get('/')->assertSee('update_gbif');
    }
}
