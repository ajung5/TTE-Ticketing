<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEmailChange extends Model {
    protected $fillable = ['ticket_id', 'actor_id', 'old_email', 'new_email', 'reason'];

    protected function casts(): array {
        return ['old_email' => 'encrypted', 'new_email' => 'encrypted'];
    }

    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
