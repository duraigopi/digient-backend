<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    protected $fillable = ['board_id', 'column_id', 'title', 'description', 'position'];

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
}
