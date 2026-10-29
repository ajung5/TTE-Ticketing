<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaMessageTemplate extends Model
{
    protected $fillable = ['service_code', 'event_type', 'body', 'updated_by'];

    public const SERVICES = [
        'Reset Passphrase' => 'Reset Passphrase',
        'Pembaruan' => 'Pembaruan Sertifikat Elektronik',
        'Pendaftaran' => 'Pendaftaran TTE Baru',
    ];

    public const EVENTS = [
        'followup' => 'Tindak lanjut',
        'completed' => 'Selesai',
    ];

    public const PLACEHOLDERS = [
        '{nama_pemohon}',
        '{nomor_tiket}',
        '{layanan}',
        '{nama_verifikator}',
        '{inisial_verifikator}',
        '{nama_instansi}',
        '{opd}',
    ];
}
