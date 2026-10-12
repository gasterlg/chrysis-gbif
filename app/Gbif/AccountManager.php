<?php

namespace App\Gbif;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\GbifChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Writes to chrysis_1.accounts_api (shared with /admin). Every change is snapshotted in
 * gbif_changes (password hashes masked) and audit-logged.
 */
class AccountManager
{
    /** The 19 legacy /admin permission flags. */
    public const FLAGS = [
        'insert_specie', 'edit_specie', 'delete_specie', 'regenerate_maps',
        'insert_type', 'edit_type', 'delete_type',
        'insert_museum', 'edit_museum', 'delete_museum',
        'insert_collection', 'edit_collection', 'delete_collection',
        'insert_author', 'edit_author', 'delete_author',
        'insert_biblio', 'edit_biblio', 'delete_biblio',
    ];

    public function create(array $data, string $password, Account $by): Account
    {
        return DB::connection('chrysis')->transaction(function () use ($data, $password, $by) {
            $account = new Account;
            $account->forceFill($this->clean($data) + ['password' => Hash::make($password)])->save();
            $this->snapshot('account', $account->id, null, $account->fresh(), 'user created', $by);
            AuditLog::record('user_created', $by->id, $by->username, "account:{$account->id}", ['username' => $account->username, 'role' => $account->role]);

            return $account;
        });
    }

    public function update(Account $account, array $data, Account $by): Account
    {
        $data = $this->clean($data);
        if ($account->id === $by->id && ($data['role'] ?? $account->role) !== 'Admin' && $account->role === 'Admin') {
            throw new InvalidArgumentException('You cannot remove your own Admin role.');
        }

        return DB::connection('chrysis')->transaction(function () use ($account, $data, $by) {
            $before = Account::query()->lockForUpdate()->findOrFail($account->id);
            $account->forceFill($data)->save();
            $dirty = array_keys(array_diff_assoc(array_map('strval', $account->only(array_keys($data))), array_map('strval', $before->only(array_keys($data)))));
            $this->snapshot('account', $account->id, $before, $account->fresh(), 'user edited', $by);
            AuditLog::record('user_updated', $by->id, $by->username, "account:{$account->id}", ['fields' => $dirty]);

            return $account;
        });
    }

    public function resetPassword(Account $account, string $password, Account $by): void
    {
        DB::connection('chrysis')->transaction(function () use ($account, $password) {
            $account->forceFill(['password' => Hash::make($password)])->save(); // bcrypt $2y$
        });
        AuditLog::record('user_password_reset', $by->id, $by->username, "account:{$account->id}");
    }

    public function setDisabled(Account $account, bool $disabled, Account $by): void
    {
        if ($disabled && $account->id === $by->id) {
            throw new InvalidArgumentException('You cannot disable your own account.');
        }
        AccountPermission::query()->updateOrCreate(['account_id' => $account->id], ['disabled' => $disabled]);
        AuditLog::record($disabled ? 'user_disabled' : 'user_enabled', $by->id, $by->username, "account:{$account->id}");
    }

    public function setUpdateGbif(Account $account, bool $on, Account $by): void
    {
        AccountPermission::query()->updateOrCreate(['account_id' => $account->id], ['update_gbif' => $on]);
        AuditLog::record($on ? 'user_update_gbif_granted' : 'user_update_gbif_revoked', $by->id, $by->username, "account:{$account->id}");
    }

    private function clean(array $data): array
    {
        $out = array_intersect_key($data, array_flip(['username', 'first_name', 'last_name', 'email', 'role', ...self::FLAGS]));
        foreach (self::FLAGS as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = $out[$f] ? 1 : 0;
            }
        }
        foreach (['first_name', 'last_name', 'email'] as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = (string) ($out[$f] ?? '');
            }
        }
        if (isset($out['role']) && ! in_array($out['role'], ['Admin', 'SuperUser'], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }

        return $out;
    }

    private function snapshot(string $entity, int $id, ?Account $before, Account $after, string $reason, Account $by): void
    {
        $mask = fn (?Account $a) => $a ? collect($a->getAttributes())->except('password')->all() : null;
        GbifChange::create(['entity' => $entity, 'ref' => (string) $id, 'before' => $mask($before), 'after' => $mask($after),
            'reason' => $reason, 'user_id' => $by->id, 'username' => $by->username]);
    }
}
