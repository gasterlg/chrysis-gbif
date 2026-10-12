<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Read-only view of chrysis_1.match (legacy Chrawler result). */
class GbifMatch extends Model
{
    protected $connection = 'chrysis';

    protected $table = 'match';

    protected $primaryKey = 'idsp';

    public $timestamps = false;

    protected $guarded = ['*'];
}
