<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClockifyTimeEntry extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'raw_data' => 'array', 'synced_at' => 'datetime'];
    }
    public function connection(): BelongsTo { return $this->belongsTo(ClockifyConnection::class, 'clockify_connection_id'); }
    public function teamboardMapping(): HasOne { return $this->hasOne(TeamboardTimelogMapping::class); }
}
