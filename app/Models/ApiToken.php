<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Opaque bearer token. The plain value is shown to the client exactly once
 * (on login/register); only its SHA-256 hash is persisted.
 *
 * Tokens expire one day after they are issued. The window is absolute, not
 * sliding: using a token does not extend it, so a leaked token is useful
 * for at most a day no matter how much traffic it sees.
 */
class ApiToken extends Model
{
    public const LIFETIME_HOURS = 24;

    protected $fillable = ['user_id', 'token_hash', 'last_used_at', 'expires_at'];

    protected $casts = [
        'user_id' => 'integer',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * The plain-text token, set only on the instance returned by issue().
     * It is never stored and never reloaded from the database.
     */
    public ?string $plainText = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Issue a token for the user; the caller reads ->plainText once.
    public static function issue(User $user): self
    {
        $plain = bin2hex(random_bytes(40));

        $token = static::create([
            'user_id' => $user->id,
            'token_hash' => static::hashToken($plain),
            'expires_at' => Carbon::now()->addHours(self::LIFETIME_HOURS),
        ]);

        $token->plainText = $plain;

        return $token;
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    // A missing expiry counts as expired: a token row that predates the
    // expiry column must not be trusted indefinitely.
    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }
}
