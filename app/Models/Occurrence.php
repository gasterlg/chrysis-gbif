<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Read-only view of chrysis_1.occurrences (legacy). */
class Occurrence extends Model
{
    protected $connection = 'chrysis';

    protected $table = 'occurrences';

    public $timestamps = false;

    protected $guarded = ['*'];
}
