<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaMessageTemplate extends Model {
    protected $fillable = ['service_code', 'event_type', 'body', 'updated_by'];

    public const SERVICES = [
        'Reset Passphrase' => 'Reset Passphrase',
        'Pembaruan' => 'Pembaruan Sertifikat Elektronik',
        'Pendaftaran' => 'Penerbitan Sertifikat Elektronik',
    ];

    private const LINK_PROMPTS = [
        'activation_link' => 'Ikuti petunjuk BSrE untuk mengaktifkan akun Anda.',
        'renewal_link' =>
            'Ikuti petunjuk BSrE untuk melanjutkan pembaruan Sertifikat Elektronik Anda.',
        'wa_verify_link' => 'Ikuti petunjuk BSrE untuk memverifikasi nomor WhatsApp Anda.',
        'reset_link' => 'Periksa email dinas Anda untuk melanjutkan reset passphrase.',
    ];

    public static function defaultLinkNotices(): array {
        $messages = [];
        foreach (self::LINK_PROMPTS as $type => $instruction) {
            $label = TicketResetResend::LINK_TYPES[$type];
            $messages[
                $type
            ] = "Yth. Bapak/Ibu {nama_pemohon},\n\nPetugas telah melakukan pengiriman tautan {$label} melalui AMS BSrE. {$instruction} Jika tautan belum diterima, hubungi petugas dengan menyebut nomor tiket {nomor_tiket}.\n\nPetugas: {nama_verifikator}\nJangan membagikan tautan pribadi, OTP, password, atau passphrase.";
        }
        return $messages;
    }

    public const EVENTS = [
        'activation_link' => 'Pengiriman Tautan Aktivasi Akun',
        'renewal_link' => 'Pengiriman Tautan Pembaruan Sertifikat',
        'wa_verify_link' => 'Pengiriman Tautan Verifikasi Nomor WhatsApp',
        'reset_link' => 'Pengiriman Tautan Reset Passphrase',
        'unverified' => 'Nomor WA Belum Terverifikasi',
        'reset' => 'Reset Passphrase',
        'reset_resend' => 'Pengiriman Ulang Link Reset Passphrase',
        'renewal' => 'Pembaruan Sertifikat',
        'registration' => 'Pendaftaran Diproses',
        'completed' => 'Selesai + Survei',
        'unregistered' => 'User Belum Terdaftar',
        'activation_required' => 'Aktivasi Akun Diperlukan',
        'certificate_not_issued' => 'SE Belum Terbit',
        'certificate_revoked' => 'SE Revoke — Penerbitan Ulang',
        'service_mismatch' => 'Perbaikan Jenis Permohonan',
        'issuance_required' => 'Penyesuaian ke Penerbitan SE (Belum Pernah Terbit)',
    ];

    public const TEMPLATE_SERVICE_CODES = [
        'activation_link' => '*',
        'renewal_link' => '*',
        'wa_verify_link' => '*',
        'reset_link' => '*',
        'unverified' => '*',
        'reset' => 'Reset Passphrase',
        'reset_resend' => 'Reset Passphrase',
        'renewal' => 'Pembaruan',
        'registration' => 'Pendaftaran',
        'completed' => '*',
        'unregistered' => '*',
        'activation_required' => '*',
        'certificate_not_issued' => '*',
        'certificate_revoked' => '*',
        'service_mismatch' => '*',
        'issuance_required' => '*',
    ];

    public const CORRECTION_EVENTS = ['service_mismatch', 'issuance_required'];

    public const VARIABLE_DESCRIPTIONS = [
        '{nama_pemohon}' => 'Nama pemohon',
        '{nomor_tiket}' => 'Nomor tiket',
        '{layanan}' => 'Jenis layanan yang diajukan',
        '{layanan_diajukan}' => 'Layanan yang diajukan pemohon',
        '{layanan_disarankan}' => 'Layanan yang disarankan verifikator',
        '{proses_aktual}' => 'Tahapan layanan berdasarkan pemeriksaan AMS',
        '{hasil_aktual}' => 'Hasil akhir pelayanan yang telah dikonfirmasi',
        '{form_url}' => 'Tautan formulir permohonan',
        '{email}' => 'Email dinas pemohon',
        '{nik}' => 'NIK tersamarkan (12 digit disembunyikan)',
        '{nip}' => 'NIP (jika diisi)',
        '{nomor_wa}' => 'Nomor WhatsApp pemohon',
        '{jabatan}' => 'Jabatan pemohon',
        '{opd}' => 'Unit kerja / OPD',
        '{keterangan}' => 'Keterangan permohonan (jika diisi)',
        '{status_verifikasi_wa}' => 'Status pemeriksaan nomor pada AMS BSrE',
        '{nama_verifikator}' => 'Nama petugas',
        '{inisial_verifikator}' =>
            'Inisial atau penanda petugas yang ditentukan admin (maks. 12 karakter)',
        '{nama_instansi}' => 'Nama instansi/layanan',
        '{survey_url}' => 'Tautan survei internal (untuk pesan selesai)',
    ];

    public const PLACEHOLDERS = [
        '{nama_pemohon}',
        '{nomor_tiket}',
        '{layanan}',
        '{layanan_diajukan}',
        '{layanan_disarankan}',
        '{proses_aktual}',
        '{hasil_aktual}',
        '{form_url}',
        '{email}',
        '{nik}',
        '{nip}',
        '{nomor_wa}',
        '{jabatan}',
        '{opd}',
        '{keterangan}',
        '{status_verifikasi_wa}',
        '{nama_verifikator}',
        '{inisial_verifikator}',
        '{nama_instansi}',
        '{survey_url}',
    ];
}
