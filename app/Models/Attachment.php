<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = [
        'card_id', 'uploaded_by', 'original_name', 'stored_path', 'mime', 'size',
    ];

    // The on-disk location is an implementation detail; clients download
    // through /api/attachments/{id}, never by path.
    protected $hidden = ['stored_path'];

    // See Board::$casts — the driver must not decide these PHP types.
    protected $casts = [
        'card_id' => 'integer',
        'uploaded_by' => 'integer',
        'size' => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
