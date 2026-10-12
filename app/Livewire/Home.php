<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Chrysis GBIF')]
class Home extends Component
{
    public function render()
    {
        try {
            $species = DB::connection('chrysis')->table('_specie')->count();
        } catch (\Throwable $e) {
            $species = null;
        }

        return view('livewire.home', ['species' => $species]);
    }
}
