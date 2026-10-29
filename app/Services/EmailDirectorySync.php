<?php

namespace App\Services;

use App\Models\EmailDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailDirectorySync {
    public function __construct(private EmailDirectoryService $directory) {}

    /** Import entire dataset atomically. A failed import never deletes the previous directory. */
    public function import(array $rows): int {
        if (count($rows) < 2) {
            throw new \RuntimeException('Spreadsheet tidak memiliki baris data.');
        }
        $header = array_map(
            fn($name) => mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $name))),
            $rows[0]
        );
        foreach (['nik', 'email gov'] as $required) {
            if (!in_array($required, $header, true)) {
                throw new \RuntimeException('Kolom wajib tidak ditemukan: ' . $required);
            }
        }
        $index = array_flip($header);
        $get = static fn(array $row, string $key) => trim((string) ($row[$index[$key] ?? -1] ?? ''));
        $records = [];
        foreach (array_slice($rows, 1) as $line => $row) {
            $nik = $get($row, 'nik');
            if ($nik === '' && $get($row, 'email gov') === '') {
                continue;
            }
            if (!preg_match('/^[0-9]{16}$/D', $nik)) {
                throw new \RuntimeException('NIK pada baris ' . ($line + 2) . ' harus berupa teks 16 digit.');
            }
            $hash = $this->directory->nikHash($nik);
            if (isset($records[$hash])) {
                throw new \RuntimeException('NIK duplikat pada baris ' . ($line + 2) . '.');
            }
            $emailRaw = $get($row, 'email gov');
            $email = $this->directory->normalizedEmail($emailRaw);
            if ($emailRaw !== '' && !$email) {
                throw new \RuntimeException('Email Gov tidak valid pada baris ' . ($line + 2) . '.');
            }
            $records[$hash] = [
                'nik_hash' => $hash,
                'nik' => $nik,
                'email' => $email,
                'nama' => $get($row, 'nama') ?: null,
                'nip' => $get($row, 'nip') ?: null,
                'jabatan' => $get($row, 'jabatan') ?: null,
                'nama_unor' => $get($row, 'nama unor') ?: null,
                'unit_kerja' => $get($row, 'unit kerja') ?: null,
                'status_pengguna' => $get($row, 'status pengguna') ?: null,
                'status_sertifikat' => $get($row, 'status sertifikat') ?: null
            ];
        }
        if ($records === []) {
            throw new \RuntimeException('Tidak ada NIK valid; direktori lama tidak diubah.');
        }
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($records, $batch) {
            foreach ($records as $record) {
                EmailDirectory::updateOrCreate(
                    ['nik_hash' => $record['nik_hash']],
                    $record + ['sync_batch' => $batch, 'synced_at' => now()]
                );
                // Existing rows must also get the new batch identifier.
                EmailDirectory::where('nik_hash', $record['nik_hash'])->update([
                    'sync_batch' => $batch,
                    'synced_at' => now()
                ]);
            }
            EmailDirectory::where('sync_batch', '!=', $batch)->delete();
        });

        return count($records);
    }

    public function fromCsv(string $path): int {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('CSV tidak dapat dibaca.');
        }
        $file = fopen($path, 'rb');
        if (!$file) {
            throw new \RuntimeException('Gagal membuka CSV.');
        }
        try {
            $rows = [];
            while (($row = fgetcsv($file, 0, ',', '"', '')) !== false) {
                $rows[] = $row;
                if (count($rows) > 100001) {
                    throw new \RuntimeException('Batas 100.000 baris CSV terlampaui.');
                }
            }

            return $this->import($rows);
        } finally {
            fclose($file);
        }
    }
}
