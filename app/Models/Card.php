<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    protected $fillable = ['board_id', 'column_id', 'title', 'description', 'position'];

    // Ids and position are compared and arithmetically adjusted (CardMover),
    // so pin the PHP types instead of trusting the database driver.
    protected $casts = [
        'board_id' => 'integer',
        'column_id' => 'integer',
        'position' => 'integer',
    ];

    // board_id is denormalised onto cards so this is one indexed lookup,
    // not a join through columns, for every access check.
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    // Step 5: files on this card, oldest first.
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('id');
    }
}
