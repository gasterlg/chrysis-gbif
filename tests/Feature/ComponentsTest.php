<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_input_renders_label_help_and_error(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['form.name' => ['Name is required.']]));
        view()->share('errors', $errors);

        $this->blade('<x-form.input name="form.name" label="Name" help="Your full name" />')
            ->assertSee('for="f-form-name"', false)
            ->assertSee('wire:model="form.name"', false)
            ->assertSee('Your full name')
            ->assertSee('Name is required.')
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_select_textarea_and_toggle(): void
    {
        view()->share('errors', new ViewErrorBag);
        $this->blade('<x-form.select name="s" label="S" :options="[\'a\' => \'Alpha\']" placeholder="Pick" />')->assertSee('Alpha')->assertSee('Pick');
        $this->blade('<x-form.textarea name="t" label="T" rows="2" />')->assertSee('rows="2"', false);
        $this->blade('<x-form.checkbox name="c" label="Enabled" toggle />')->assertSee('peer sr-only', false)->assertSee('Enabled');
    }

    public function test_modal_and_confirm_components(): void
    {
        $this->blade('<x-modal name="demo" title="Hello">Body</x-modal>')->assertSee('data-modal="demo"', false)->assertSee('Hello')->assertSee('Body');
        $this->blade('<x-confirm-dialog />')->assertSee('data-confirm-dialog', false);
        $this->blade('<x-confirm-button method="applyBatch" :args="[5]" title="Apply?" danger>Apply</x-confirm-button>')
            ->assertSee('applyBatch')->assertSee('bg-red-600', false);
    }

    public function test_layout_contains_toasts_confirm_nav_and_session_toast(): void
    {
        $this->actingAs(Account::find(1))
            ->withSession(['toast' => ['type' => 'success', 'message' => 'Saved it']])
            ->get('/')
            ->assertOk()
            ->assertSee('data-toasts', false)
            ->assertSee('data-confirm-dialog', false)
            ->assertSee('Saved it')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Log out');
    }

    public function test_dashboard_counts(): void
    {
        $this->actingAs(Account::find(1))->get('/')->assertSee('data-stat="species">3', false);
    }
}
