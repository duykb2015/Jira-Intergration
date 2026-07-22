<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamboardTimelogMapping extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['raw_request' => 'array', 'raw_response' => 'array', 'synced_at' => 'datetime']; }
}
