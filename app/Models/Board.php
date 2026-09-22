<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Board extends Model
{
    protected $fillable = ['owner_id', 'name'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Shared editors. Plain belongsToMany over board_members — no pivot
     * model needed because the pivot carries no data beyond the two ids.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_members')->withTimestamps();
    }

    /**
     * Boards the user can see: owned OR shared with them.
     * Kept as a scope so index() and access checks share one definition.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)
              ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }
}
