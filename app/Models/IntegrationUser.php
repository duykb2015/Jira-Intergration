<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationUser extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_active' => 'boolean', 'last_synced_at' => 'datetime']; }
    public function connection(): BelongsTo { return $this->belongsTo(ClockifyConnection::class, 'clockify_connection_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
