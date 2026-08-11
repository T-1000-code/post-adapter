<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'access_token'])]
class BufferConnection extends Model
{
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(BufferChannel::class);
    }

    public function channelFor(string $service): ?BufferChannel
    {
        return $this->channels->firstWhere('service', $service);
    }

    public function isConnected(string $service = 'twitter'): bool
    {
        return ! is_null($this->channelFor($service));
    }
}
