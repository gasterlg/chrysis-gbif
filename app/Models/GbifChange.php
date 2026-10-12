<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GbifChange extends Model
{
    protected $connection = 'chrysis';

    protected $table = 'gbif_changes';

    protected $guarded = [];

    protected $casts = ['before' => 'array', 'after' => 'array', 'rolled_back_at' => 'datetime'];
}
