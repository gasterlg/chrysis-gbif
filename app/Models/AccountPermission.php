<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** App-side permissions for an accounts_api user (stored in the app DB, not chrysis_1). */
class AccountPermission extends Model
{
    protected $fillable = ['account_id', 'update_gbif', 'disabled'];

    protected $casts = ['update_gbif' => 'boolean', 'disabled' => 'boolean'];
}
