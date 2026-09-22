<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opaque bearer token. The plain value is shown to the client exactly once
 * (on login/register); only its SHA-256 hash is persisted.
 */
class ApiToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'last_used_at'];

    protected $casts = [
        'user_id' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a new token for the user and return the plain-text value.
     */
    public static function issue(User $user): string
    {
        $plain = bin2hex(random_bytes(40));

        static::create([
            'user_id' => $user->id,
            'token_hash' => static::hashToken($plain),
        ]);

        return $plain;
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
