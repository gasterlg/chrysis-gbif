<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Support\Help;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HelpSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_route_has_a_guide_partial(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! in_array('GET', $route->methods()) || str_starts_with($route->uri(), 'livewire') || ! in_array('web', $route->gatherMiddleware())) {
                continue;
            }
            if (in_array($name, ['storage.local', 'up'], true)) {
                continue;
            }
            $this->assertNotNull(Help::partialFor($name), "No help partial for route {$name}");
        }
    }

    public function test_sidebar_rendered_with_page_specific_guide(): void
    {
        AccountPermission::updateOrCreate(['account_id' => 2], ['update_gbif' => true]);
        $this->actingAs(Account::find(2));
        $pages = [
            '/' => ['help.home', 'Match review'],
            '/species' => ['help.species-index', 'Status colours'],
            '/species/1' => ['help.species-show', 'Import from GBIF'],
            '/review/matches' => ['help.matches', 'never bulk-accepted'],
            '/review/occurrences' => ['help.occurrences', 'Far from range'],
            '/review/changes' => ['help.changes', 'Roll back'],
            '/history' => ['help.history', 'diary'],
            '/exports' => ['help.exports', 'CC BY-NC'],
            '/datasets' => ['help.datasets', 'NHM London'],
            '/users' => ['help.users', 'Update GBIF'],
            '/audit' => ['help.audit', 'failed'],
        ];
        foreach ($pages as $url => [$partial, $text]) {
            $this->get($url)->assertOk()->assertSee('data-help-sidebar="'.$partial.'"', false)->assertSee($text)->assertSee('Typical workflow');
        }
    }

    public function test_login_page_has_guide_without_workflow(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-help-sidebar="help.login"', false)->assertDontSee('Typical workflow');
    }

    public function test_unknown_route_has_no_guide(): void
    {
        $this->assertNull(Help::partialFor('nope'));
        $this->assertNull(Help::partialFor(null));
    }
}
