<?php

namespace App\Services;

use App\Models\EmailDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailDirectorySync {
    /** @var array{created: int, updated: int, unchanged: int, deleted: int} */
    private array $lastStats = [
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'deleted' => 0,
    ];

    public function __construct(private EmailDirectoryService $directory) {}

    /** @return array{created: int, updated: int, unchanged: int, deleted: int} */
    public function stats(): array {
        return $this->lastStats;
    }

    /**
     * Import atomik: hanya INSERT/UPDATE baris berubah dan DELETE baris yang
     * benar-benar hilang dari snapshot. Checkpoint kesegaran diperbarui terpisah.
     *
     * Jika lebih dari 25% data lama (dan lebih dari 2 baris) akan terhapus,
     * sinkronisasi gagal agar spreadsheet terpotong tidak menghapus direktori.
     */
    public function import(array $rows, bool $allowLargeRemoval = false): int {
        $this->lastStats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];
        if (count($rows) < 2) {
            throw new \RuntimeException('Spreadsheet tidak memiliki baris data.');
        }
        if (count($rows) > 100001) {
            throw new \RuntimeException('Batas 100.000 baris direktori terlampaui.');
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

            $hash = $nik !== ''
                ? $this->directory->nikHash($nik)
                : $this->directory->nipOnlyHash($nip);
            if (isset($records[$hash])) {
                throw new \RuntimeException('NIK/NIP duplikat pada baris ' . ($line + 2) . '.');
            }
            $email = $this->directory->normalizedEmail($emailRaw);
            if ($emailRaw !== '' && !$email) {
                throw new \RuntimeException('Email Gov tidak valid pada baris ' . ($line + 2) . '.');
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
        $syncedAt = now();
        $stats = DB::transaction(function () use ($records, $batch, $syncedAt, $allowLargeRemoval) {
            // Snapshot hanya menyimpan record baru yang belum ditemukan di database.
            $pending = $records;
            $removeIds = [];
            $updated = 0;
            $unchanged = 0;
            $existingCount = EmailDirectory::query()->count();

            // Hindari load seluruh Eloquent collection ke memori sekaligus.
            EmailDirectory::query()->chunkById(400, function ($chunk) use (
                &$pending,
                &$removeIds,
                &$updated,
                &$unchanged,
                $batch,
                $syncedAt,
            ) {
                foreach ($chunk as $existing) {
                    $key = $existing->nik_hash;
                    if (!array_key_exists($key, $pending)) {
                        $removeIds[] = $existing->id;
                        continue;
                    }

                    $record = $pending[$key];
                    unset($pending[$key]);
                    $changes = [];
                    foreach ($record as $column => $value) {
                        if ($column !== 'nik_hash' && $existing->getAttribute($column) !== $value) {
                            $changes[$column] = $value;
                        }
                    }
                    if ($changes === []) {
                        $unchanged++;
                        continue;
                    }

                    // Model casts mengenkripsi NIK/email; jangan bypass lewat DB::upsert.
                    $existing->fill($changes);
                    $existing->sync_batch = $batch;
                    $existing->synced_at = $syncedAt;
                    $existing->save();
                    $updated++;
                }
            });

            $removeCount = count($removeIds);
            if (
                !$allowLargeRemoval &&
                $removeCount > 2 &&
                $removeCount * 4 > $existingCount
            ) {
                throw new \RuntimeException(
                    'Sinkronisasi ditolak: lebih dari 25% direktori lama akan terhapus ' .
                        "({$removeCount}/{$existingCount}). Periksa kelengkapan spreadsheet; " .
                        'gunakan --allow-large-removal hanya setelah verifikasi manual.',
                );
            }

            foreach ($pending as $record) {
                EmailDirectory::create(
                    $record + ['sync_batch' => $batch, 'synced_at' => $syncedAt],
                );
            }
            foreach (array_chunk($removeIds, 400) as $ids) {
                EmailDirectory::whereIn('id', $ids)->delete();
            }

            // Satu penulisan checkpoint per sinkronisasi sukses diperlukan supaya
            // data tetap dianggap segar sekalipun semua baris pegawai tidak berubah.
            DB::table('email_directory_sync_states')->updateOrInsert(
                ['name' => 'email_directory'],
                [
                    'last_success_at' => $syncedAt,
                    'row_count' => count($records),
                    'updated_at' => $syncedAt,
                ],
            );

            return [
                'created' => count($pending),
                'updated' => $updated,
                'unchanged' => $unchanged,
                'deleted' => $removeCount,
            ];
        });
        $this->lastStats = $stats;
        return count($records);
    }

    public function fromCsv(string $path, bool $allowLargeRemoval = false): int {
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
            return $this->import($rows, $allowLargeRemoval);
        } finally {
            fclose($file);
        }
    }
}
