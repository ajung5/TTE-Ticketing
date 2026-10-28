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
        'nama_user', 'no_wa', 'layanan', 'status',
        'ticket_number', 'external_submission_id', 'source', 'nip', 'opd',
        'jabatan', 'email', 'keterangan', 'claimed_by', 'claimed_at',
        'completed_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->oldest();
    }

    public function whatsappNotifications(): HasMany
    {
        return $this->hasMany(WaNotification::class);
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
