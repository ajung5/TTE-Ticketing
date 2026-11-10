<?php

namespace App\Services;

use App\Models\EmailDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailDirectorySync {
    public function __construct(private EmailDirectoryService $directory) {}

    /** Import atomic: NIK 16 digit atau NIP 18 digit wajib tersedia pada tiap baris bermakna. */
    public function import(array $rows): int {
        if (count($rows) < 2) {
            throw new \RuntimeException('Spreadsheet tidak memiliki baris data.');
        }
        $header = array_map(
            fn($name) => mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $name))),
            $rows[0],
        );
        if (!in_array('email gov', $header, true)) {
            throw new \RuntimeException('Kolom wajib tidak ditemukan: email gov');
        }
        if (!in_array('nik', $header, true) && !in_array('nip', $header, true)) {
            throw new \RuntimeException('Spreadsheet harus memiliki kolom NIK atau NIP.');
        }
        $index = array_flip($header);
        $get = static fn(array $row, string $key) => trim(
            (string) ($row[$index[$key] ?? -1] ?? ''),
        );
        $records = [];
        $seenNip = [];

        foreach (array_slice($rows, 1) as $line => $row) {
            $nik = $get($row, 'nik');
            $nip = $get($row, 'nip');
            $emailRaw = $get($row, 'email gov');
            if ($nik === '' && $nip === '' && $emailRaw === '') {
                continue;
            }

            if ($nik !== '' && !preg_match('/\A[0-9]{16}\z/', $nik)) {
                throw new \RuntimeException(
                    'NIK pada baris ' . ($line + 2) . ' harus berupa teks 16 digit.',
                );
            }
            $nipValid = preg_match('/\A[0-9]{18}\z/', $nip) === 1;
            if ($nik === '' && !$nipValid) {
                throw new \RuntimeException(
                    'Baris ' . ($line + 2) . ' harus memiliki NIK 16 digit atau NIP 18 digit.',
                );
            }
            if (strlen($nip) > 32) {
                throw new \RuntimeException('NIP terlalu panjang pada baris ' . ($line + 2) . '.');
            }
            if ($nipValid) {
                if (isset($seenNip[$nip])) {
                    throw new \RuntimeException('NIP duplikat pada baris ' . ($line + 2) . '.');
                }
                $seenNip[$nip] = true;
            }

            $hash =
                $nik !== '' ? $this->directory->nikHash($nik) : $this->directory->nipOnlyHash($nip);
            if (isset($records[$hash])) {
                throw new \RuntimeException('NIK/NIP duplikat pada baris ' . ($line + 2) . '.');
            }
            $email = $this->directory->normalizedEmail($emailRaw);
            if ($emailRaw !== '' && !$email) {
                throw new \RuntimeException(
                    'Email Gov tidak valid pada baris ' . ($line + 2) . '.',
                );
            }

            $records[$hash] = [
                'nik_hash' => $hash,
                'nik' => $nik !== '' ? $nik : null,
                'email' => $email,
                'nama' => $get($row, 'nama') ?: null,
                'nip' => $nip ?: null,
                'jabatan' => $get($row, 'jabatan') ?: null,
                'nama_unor' => $get($row, 'nama unor') ?: null,
                'unit_kerja' => $get($row, 'unit kerja') ?: null,
                'uptd' => in_array($get($row, 'uptd'), ['', '-'], true) ? null : $get($row, 'uptd'),
                'status_pengguna' => $get($row, 'status pengguna') ?: null,
                'status_sertifikat' => $get($row, 'status sertifikat') ?: null,
            ];
        }
        if ($records === []) {
            throw new \RuntimeException('Tidak ada NIK/NIP valid; direktori lama tidak diubah.');
        }
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($records, $batch) {
            foreach ($records as $record) {
                EmailDirectory::updateOrCreate(
                    ['nik_hash' => $record['nik_hash']],
                    $record + ['sync_batch' => $batch, 'synced_at' => now()],
                );
                EmailDirectory::where('nik_hash', $record['nik_hash'])->update([
                    'sync_batch' => $batch,
                    'synced_at' => now(),
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
