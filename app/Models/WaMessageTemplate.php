<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaMessageTemplate extends Model {
    protected $fillable = ['service_code', 'event_type', 'body', 'updated_by'];

    public const SERVICES = [
        'Reset Passphrase' => 'Reset Passphrase',
        'Pembaruan' => 'Pembaruan Sertifikat Elektronik',
        'Pendaftaran' => 'Penerbitan Sertifikat Elektronik'
    ];

    // Enam jenis notifikasi yang aktif. Template umum memakai service_code '*'.
    public const EVENTS = [
        'unverified' => 'Nomor WA Belum Terverifikasi',
        'reset' => 'Reset Passphrase',
        'renewal' => 'Pembaruan Sertifikat',
        'registration' => 'Pendaftaran Diproses',
        'completed' => 'Selesai + Survei',
        'unregistered' => 'User Belum Terdaftar',
        'certificate_not_issued' => 'SE Belum Terbit',
        'service_mismatch' => 'Perbaikan Jenis Permohonan'
    ];

    public const TEMPLATE_SERVICE_CODES = [
        'unverified' => '*',
        'reset' => 'Reset Passphrase',
        'renewal' => 'Pembaruan',
        'registration' => 'Pendaftaran',
        'completed' => '*',
        'unregistered' => '*',
        'certificate_not_issued' => '*',
        'service_mismatch' => '*'
    ];

    public const VARIABLE_DESCRIPTIONS = [
        '{nama_pemohon}' => 'Nama pemohon',
        '{nomor_tiket}' => 'Nomor tiket',
        '{layanan}' => 'Jenis layanan',
        '{layanan_diajukan}' => 'Layanan yang diajukan pemohon',
        '{layanan_disarankan}' => 'Layanan yang disarankan verifikator',
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
        '{inisial_verifikator}' => 'Inisial petugas',
        '{nama_instansi}' => 'Nama instansi/layanan',
        '{survey_url}' => 'Tautan survei internal (untuk pesan selesai)'
    ];

    public const PLACEHOLDERS = [
        '{nama_pemohon}',
        '{nomor_tiket}',
        '{layanan}',
        '{layanan_diajukan}',
        '{layanan_disarankan}',
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
        '{survey_url}'
    ];
}
