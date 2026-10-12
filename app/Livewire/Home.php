<?php

namespace App\Livewire;

use App\Models\Occurrence;
use App\Models\Species;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Home extends Component
{
    public function render()
    {
        $match = DB::connection('chrysis')->table('match')
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN gbif_id > 0 THEN 1 ELSE 0 END) AS matched')
            ->first();

        return view('livewire.home', [
            'species' => Species::count(),
            'matched' => (int) $match->matched,
            'failed' => (int) $match->total - (int) $match->matched,
            'occurrences' => Occurrence::count(),
        ]);
    }
}
