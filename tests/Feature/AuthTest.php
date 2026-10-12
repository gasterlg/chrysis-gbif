<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_and_guests_are_redirected(): void
    {
        $this->get('/login')->assertOk()->assertSee('Username or e-mail');
        $this->get('/species')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_with_email_and_password(): void
    {
        Livewire::test(Login::class)
            ->set('form.login', 'user1@example.test')
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs(Account::find(1));
        $this->assertDatabaseHas('gbif_audit_log', ['action' => 'login', 'account_id' => 1]);
    }

    public function test_login_with_username(): void
    {
        Livewire::test(Login::class)->set('form.login', 'admin')->set('form.password', 'password')->call('login')->assertHasNoErrors();
        $this->assertAuthenticatedAs(Account::find(2));
    }

    public function test_2a_hash_is_accepted_and_rehashed_to_2y(): void
    {
        $this->assertStringStartsWith('$2a$', Account::find(3)->password);

        Livewire::test(Login::class)->set('form.login', 'franco')->set('form.password', 'secret-2a')->call('login')->assertHasNoErrors();

        $this->assertAuthenticatedAs(Account::find(3));
        $fresh = Account::find(3)->password;
        $this->assertStringStartsWith('$2y$', $fresh);
        $this->assertTrue(password_verify('secret-2a', $fresh));
    }

    public function test_wrong_password_fails_and_is_audited(): void
    {
        Livewire::test(Login::class)->set('form.login', 'puzzo')->set('form.password', 'nope')->call('login')
            ->assertHasErrors('form.login');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'login_failed')->count());
    }

    public function test_validation_errors(): void
    {
        Livewire::test(Login::class)->call('login')->assertHasErrors(['form.login' => 'required', 'form.password' => 'required']);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        RateLimiter::clear('login:puzzo|127.0.0.1');
        $c = Livewire::test(Login::class)->set('form.login', 'puzzo')->set('form.password', 'wrong');
        for ($i = 0; $i < 5; $i++) {
            $c->call('login');
        }
        // Even the right password is refused while locked out.
        $c->set('form.password', 'password')->call('login')->assertHasErrors('form.login')->assertSee('Too many login attempts');
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(Account::find(1))->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('gbif_audit_log', ['action' => 'logout', 'account_id' => 1]);
    }
}
