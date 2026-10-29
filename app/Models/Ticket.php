<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_user',
        'no_wa',
        'layanan',
        'status',
        'ticket_number',
        'external_submission_id',
        'source',
        'nip',
        'opd',
        'jabatan',
        'email',
        'keterangan',
        'claimed_by',
        'claimed_at',
        'completed_at',
        'resolution_note',
        'nik',
        'wa_verification_status',
        'wa_verification_note',
        'wa_checked_at',
        'wa_checked_by',
        'consent_accepted_at',
        'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
            'wa_checked_at' => 'datetime',
            'nik' => 'encrypted',
            'consent_accepted_at' => 'datetime',
        ];
    }

    public function claimant(): BelongsTo
    {
        // Menjaga referensi historis verifikator pada tiket selesai.
        return $this->belongsTo(User::class, 'claimed_by')->withTrashed();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->oldest();
    }

    public function whatsappNotifications(): HasMany
    {
        return $this->hasMany(WaNotification::class);
    }

    public function getMaskedNikAttribute(): string
    {
        $value = (string) ($this->nik ?? '');
        return preg_match('/^[0-9]{16}$/', $value) ? str_repeat('•', 12).substr($value, -4) : '-';
    }

    public function getWaVerificationLabelAttribute(): string
    {
        return match ($this->wa_verification_status) {
            'verified' => 'Terverifikasi di AMS BSrE',
            'unverified' => 'Belum terverifikasi di AMS BSrE',
            default => 'Belum diperiksa',
        };
    }

    public function getServiceLabelAttribute(): string
    {
        return match ($this->layanan) {
            'Pembaruan' => 'Pembaruan Sertifikat Elektronik',
            'Pendaftaran' => 'Pendaftaran TTE Baru',
            default => 'Reset Passphrase TTE',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'In Progress' => 'Diproses',
            'Done' => 'Selesai',
            default => 'Baru',
        };
    }
}
