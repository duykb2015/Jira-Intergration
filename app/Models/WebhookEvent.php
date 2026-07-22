<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['payload' => 'array', 'raw_response' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
    }
    public function connection(): BelongsTo { return $this->belongsTo(ClockifyConnection::class, 'clockify_connection_id'); }
}
