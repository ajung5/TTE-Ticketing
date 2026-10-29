<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaMessageTemplate extends Model {
    protected $fillable = ['service_code', 'event_type', 'body', 'updated_by'];

    public const SERVICES = [
        'Reset Passphrase' => 'Reset Passphrase',
        'Pembaruan' => 'Pembaruan Sertifikat Elektronik',
        'Pendaftaran' => 'Pendaftaran TTE Baru'
    ];

    public const EVENTS = [
        'unverified' => 'Nomor WA belum terverifikasi',
        'followup' => 'Tindak lanjut',
        'inbox_reminder' => 'Pengingat cek inbox email',
        'completed' => 'Selesai'
    ];

    public const VARIABLE_DESCRIPTIONS = [
        '{nama_pemohon}' => 'Nama pemohon',
        '{nomor_tiket}' => 'Nomor tiket',
        '{layanan}' => 'Jenis layanan',
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
