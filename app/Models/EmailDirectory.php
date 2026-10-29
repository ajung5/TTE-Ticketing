<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDirectory extends Model
{
    protected $fillable = [
        'nik_hash', 'nik', 'nama', 'nip', 'email', 'jabatan', 'nama_unor',
        'unit_kerja', 'status_pengguna', 'status_sertifikat', 'sync_batch', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted', 'email' => 'encrypted',
            'synced_at' => 'datetime',
        ];
    }
}
