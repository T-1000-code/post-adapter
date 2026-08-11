<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['buffer_connection_id', 'service', 'channel_id', 'channel_name'])]
class BufferChannel extends Model
{
    public function connection(): BelongsTo
    {
        return $this->belongsTo(BufferConnection::class, 'buffer_connection_id');
    }
}
