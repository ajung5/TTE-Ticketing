<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model {
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
        'uptd',
        'jabatan',
        'email',
        'keterangan',
        'claimed_by',
        'claimed_at',
        'completed_at',
        'resolution_note',
        'nik',
        'activation_status',
        'certificate_issuance_status',
        'certificate_validity_status',
        'recommended_service',
        'service_alignment_status',
        'actual_process',
        'service_outcome',
        'wa_verification_status',
        'wa_verification_note',
        'wa_checked_at',
        'wa_checked_by',
        'consent_accepted_at',
        'signature_path',
        'applicant_nik_hash',
        'applicant_nip_hash',
        'directory_email',
        'duplicate_attempts',
        'last_duplicate_attempt_at',
        'awaiting_applicant_action',
        'email_corrected_at',
        'email_corrected_by',
    ];

    protected function casts(): array {
        return [
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
            'wa_checked_at' => 'datetime',
            'nik' => 'encrypted',
            'directory_email' => 'encrypted',
            'duplicate_attempts' => 'integer',
            'last_duplicate_attempt_at' => 'datetime',
            'awaiting_applicant_action' => 'boolean',
            'email_corrected_at' => 'datetime',
            'consent_accepted_at' => 'datetime',
        ];
    }

    public function claimant(): BelongsTo {
        return $this->belongsTo(User::class, 'claimed_by')->withTrashed();
    }

    public function activities(): HasMany {
        return $this->hasMany(TicketActivity::class)->oldest();
    }

    public function whatsappNotifications(): HasMany {
        return $this->hasMany(WaNotification::class);
    }

    public function getMaskedNikAttribute(): string {
        $value = (string) ($this->nik ?? '');
        return preg_match('/^[0-9]{16}$/', $value) ? str_repeat('•', 12) . substr($value, -4) : '-';
    }

    public function getWaVerificationLabelAttribute(): string {
        return match ($this->wa_verification_status) {
            'verified' => 'Terverifikasi di AMS BSrE',
            'unverified' => 'Belum terverifikasi di AMS BSrE',
            default => 'Belum diperiksa',
        };
    }

    public function getActivationLabelAttribute(): string {
        return match ($this->activation_status) {
            'activated' => 'Sudah aktivasi',
            'not_activated' => 'Belum aktivasi',
            default => 'Belum diperiksa',
        };
    }

    public function getCertificateStatusLabelAttribute(): string {
        return match (true) {
            $this->certificate_issuance_status === 'not_issued' => 'SE belum pernah terbit',
            $this->certificate_issuance_status === 'issued' &&
                $this->certificate_validity_status === 'active'
                => 'SE aktif',
            $this->certificate_issuance_status === 'issued' &&
                $this->certificate_validity_status === 'expired'
                => 'SE kedaluwarsa',
            $this->certificate_issuance_status === 'issued' &&
                $this->certificate_validity_status === 'revoked'
                => 'SE dicabut (Revoke)',
            $this->certificate_issuance_status === 'issued' => 'Masa berlaku belum diperiksa',
            default => 'Belum diperiksa',
        };
    }

    public function getAmsSummaryLabelAttribute(): string {
        if (!$this->wa_checked_at || !$this->activation_status) {
            return 'Belum diperiksa';
        }
        if ($this->activation_status === 'not_activated') {
            return 'Akun belum aktivasi';
        }
        if ($this->wa_verification_status === 'unverified') {
            return 'WA belum terverifikasi';
        }
        if ($this->wa_verification_status !== 'verified') {
            return 'WA belum diperiksa';
        }
        return $this->certificate_status_label;
    }

    public function getServiceLabelAttribute(): string {
        return match ($this->layanan) {
            'Pembaruan' => 'Pembaruan Sertifikat Elektronik',
            'Pendaftaran' => 'Pendaftaran TTE Baru',
            default => 'Reset Passphrase TTE',
        };
    }

    public function getServiceAlignmentLabelAttribute(): string {
        return match ($this->service_alignment_status) {
            'suitable' => 'Sesuai',
            'unsuitable' => 'Belum sesuai',
            default => 'Belum dievaluasi',
        };
    }

    public function getActualProcessLabelAttribute(): string {
        return match ($this->actual_process) {
            'account_activation' => 'Aktivasi akun',
            'whatsapp_verification' => 'Verifikasi WhatsApp',
            'set_passphrase' => 'Set Passphrase / Penerbitan SE',
            'reset_passphrase' => 'Reset Passphrase',
            'certificate_renewal' => 'Pembaruan Sertifikat Elektronik',
            'certificate_issuance' => 'Penerbitan Sertifikat Elektronik',
            'registration_followup' => 'Tindak lanjut permohonan Penerbitan SE',
            'certificate_reissuance' => 'Penerbitan Ulang Sertifikat Elektronik',
            'service_correction' => 'Klarifikasi jenis layanan',
            default => 'Belum ditetapkan',
        };
    }

    public function getServiceOutcomeLabelAttribute(): string {
        return match ($this->service_outcome) {
            'passphrase_reset' => 'Reset Passphrase selesai',
            'certificate_issued' => 'Sertifikat Elektronik berhasil diterbitkan',
            'registration_processed'
                => 'Permohonan Penerbitan SE telah diproses (bukan konfirmasi sertifikat telah terbit)',
            'certificate_renewed' => 'Sertifikat Elektronik berhasil diperbarui',
            'certificate_reissued' => 'Sertifikat Elektronik berhasil diterbitkan ulang',
            default => 'Hasil belum ditetapkan',
        };
    }

    public function getStatusLabelAttribute(): string {
        return match ($this->status) {
            'In Progress' => 'Diproses',
            'Done' => 'Selesai',
            'Returned' => 'Perlu Pengajuan Ulang',
            default => 'Baru',
        };
    }
}
