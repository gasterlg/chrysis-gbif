<?php

namespace App\Jobs;

use App\Gbif\MatchProposer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProposeMatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $idsp) {}

    public function handle(MatchProposer $proposer): void
    {
        $proposer->propose($this->idsp);
    }
}
