<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Manual AMS link deliveries share the original reset-resend history table. */
class TicketResetResend extends Model {
    public const LINK_TYPES = [
        'activation_link' => 'Aktivasi akun',
        'renewal_link' => 'Pembaruan sertifikat',
        'wa_verify_link' => 'Verifikasi nomor WhatsApp',
        'reset_link' => 'Reset Passphrase',
    ];

    public const REASONS = [
        'initial' => 'Pengiriman pertama',
        'not_received' => 'Kirim ulang — tautan belum diterima',
        'expired' => 'Kirim ulang — tautan kedaluwarsa',
        'cannot_open' => 'Kirim ulang — tautan tidak dapat dibuka',
    ];

    protected $fillable = [
        'link_type',
        'ticket_id',
        'verifier_id',
        'reason',
        'message',
        'wa_opened_at',
        'wa_confirmed_at',
    ];

    protected function casts(): array {
        return ['wa_opened_at' => 'datetime', 'wa_confirmed_at' => 'datetime'];
    }
}
