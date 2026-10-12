<?php

namespace App\Livewire\Species;

use App\Gbif\GbifClient;
use App\Gbif\GbifException;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/** Live GBIF match info for one species; lazy so the page renders without waiting for GBIF. */
#[Lazy]
class GbifPanel extends Component
{
    public string $name;

    public ?string $author = null;

    public ?int $localKey = null;

    public function refresh(GbifClient $gbif): void
    {
        cache()->forget('gbif:match:'.md5(json_encode($this->query())));
        $this->dispatch('toast', type: 'success', message: 'GBIF match refreshed.');
    }

    private function query(): array
    {
        return array_filter(['scientificName' => trim($this->name.' '.($this->author ?? '')), 'family' => 'Chrysididae']);
    }

    public function placeholder()
    {
        return <<<'HTML'
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" data-gbif-panel="loading">
            <h2 class="mb-3 text-base font-semibold text-slate-800">Live GBIF match</h2>
            <div class="animate-pulse space-y-2"><div class="h-4 w-2/3 rounded bg-slate-200"></div><div class="h-4 w-1/2 rounded bg-slate-200"></div><div class="h-4 w-1/3 rounded bg-slate-200"></div></div>
        </section>
        HTML;
    }

    public function render(GbifClient $gbif)
    {
        $result = $error = null;
        $count = null;

        try {
            $q = $this->query();
            $result = $gbif->matchSpecies($q['scientificName'], ['family' => $q['family']]);
            if ($result['usage_key']) {
                $count = $gbif->occurrenceCount($result['accepted_key'] ?? $result['usage_key']);
            }
        } catch (GbifException $e) {
            report($e);
            $error = 'GBIF is not reachable right now ('.$e->getMessage().').';
        }

        $agrees = $result && $this->localKey && in_array($this->localKey, array_filter([$result['usage_key'], $result['accepted_key']]), true);

        return view('livewire.species.gbif-panel', compact('result', 'error', 'count', 'agrees'));
    }
}
