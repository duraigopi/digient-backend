<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Lumen\Auth\Authorizable;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory;

    /**
     * The attributes that are mass assignable.
     * "password" added so AuthController::register can User::create() in one
     * call; it is always passed through Hash::make first.
     *
     * @var string[]
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var string[]
     */
    protected $hidden = [
        'password',
        // "pivot" added: when users are loaded as board members the
        // board_members join columns would otherwise leak into the JSON.
        'pivot',
    ];

    // Added so a user's sessions can be listed/revoked (e.g. cascade on delete).
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    // Boards this user created (owner-only rights: share, rename, delete).
    public function ownedBoards(): HasMany
    {
        return $this->hasMany(Board::class, 'owner_id');
    }

    // Boards shared with this user by their owners.
    public function sharedBoards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class, 'board_members')->withTimestamps();
    }
}
