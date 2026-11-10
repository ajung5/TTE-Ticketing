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

    public const EVENTS = [
        'unverified' => 'Nomor WA Belum Terverifikasi',
        'reset' => 'Reset Passphrase',
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
        'unverified' => '*',
        'reset' => 'Reset Passphrase',
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
