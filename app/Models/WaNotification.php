<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaNotification extends Model
{
    protected $fillable = ['ticket_id', 'verifier_id', 'event_type', 'message', 'opened_at', 'confirmed_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
