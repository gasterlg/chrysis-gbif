<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A user from the legacy chrysis_1.accounts_api table (shared with /admin).
 * Read through the "chrysis" connection; the app never alters its schema.
 */
class Account extends Authenticatable
{
    protected $connection = 'chrysis';

    protected $table = 'accounts_api';

    public $timestamps = false;

    /** accounts_api has no remember_token column. */
    protected $rememberTokenName = '';

    protected $hidden = ['password'];

    protected $guarded = ['id'];

    public function permission(): HasOne
    {
        return $this->hasOne(AccountPermission::class, 'account_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    public function canUpdateGbif(): bool
    {
        return (bool) AccountPermission::query()->where('account_id', $this->id)->value('update_gbif');
    }

    public function displayName(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : $this->username;
    }
}
