<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable(['internal_user_id', 'api_token', 'clockify_user_id', 'clockify_workspace_id', 'clockify_email', 'workspace_name', 'webhook_secret_hash', 'status', 'last_checked_at', 'last_synced_at'])]
class ClockifyConnection extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $connection): void {
            $connection->uuid ??= (string) Str::uuid();
            $connection->webhook_secret_hash ??= Hash::make(Str::random(64));
        });
    }

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'last_checked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_user_id');
    }

    public function integrationUser(): HasOne
    {
        return $this->hasOne(IntegrationUser::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ClockifyTask::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ClockifyTimeEntry::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function matchesWebhookSecret(string $secret): bool
    {
        return Hash::check($secret, $this->webhook_secret_hash);
    }
}
