<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'gbif_audit_log';

    const UPDATED_AT = null;

    protected $fillable = ['account_id', 'username', 'action', 'target', 'ip', 'meta'];

    protected $casts = ['meta' => 'array'];

    public static function record(string $action, ?int $accountId = null, ?string $username = null, ?string $target = null, array $meta = []): self
    {
        return static::create([
            'account_id' => $accountId,
            'username' => $username,
            'action' => $action,
            'target' => $target,
            'ip' => request()?->ip(),
            'meta' => $meta ?: null,
        ]);
    }
}
