<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only record of manual AMS link resends and separate WhatsApp confirmations. */
class TicketResetResend extends Model {
    protected $fillable = [
        'ticket_id', 'verifier_id', 'reason', 'message', 'wa_opened_at', 'wa_confirmed_at',
    ];

    protected function casts(): array {
        return ['wa_opened_at' => 'datetime', 'wa_confirmed_at' => 'datetime'];
    }
}
