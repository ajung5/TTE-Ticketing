<?php

use App\Models\EmailDirectory;
use App\Services\EmailDirectoryService;
use App\Services\EmailDirectorySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function incrementalDirectoryRowsV620(int $count = 2): array {
    $rows = [['NIK', 'NIP', 'Email Gov', 'NAMA', 'JABATAN', 'UNIT KERJA']];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = [
            '327301' . sprintf('%010d', $i),
            '',
            'pegawai' . $i . '@subang.go.id',
            'Pegawai ' . $i,
            'Analis',
            'Diskominfo',
        ];
    }
    return $rows;
}

it('only refreshes the sync checkpoint when Google Sheets data is unchanged', function () {
    config()->set('email_directory.max_age_hours', 1);
    $sync = app(EmailDirectorySync::class);
    $rows = incrementalDirectoryRowsV620();
    $sync->import($rows);

    $original = EmailDirectory::query()->orderBy('id')->get();
    $baseline = $original->map(fn($item) => [
        'id' => $item->id,
        'updated_at' => $item->updated_at->toDateTimeString(),
        'synced_at' => $item->synced_at->toDateTimeString(),
        'sync_batch' => $item->sync_batch,
        'encrypted_email' => $item->getRawOriginal('email'),
        'encrypted_nik' => $item->getRawOriginal('nik'),
    ])->all();
    $checkpoint = DB::table('email_directory_sync_states')
        ->where('name', 'email_directory')->value('last_success_at');

    $this->travel(2)->hours();
    expect(app(EmailDirectoryService::class)->available())->toBeFalse();
    expect($sync->import($rows))->toBe(2);
    expect($sync->stats())->toBe([
        'created' => 0, 'updated' => 0, 'unchanged' => 2, 'deleted' => 0,
    ]);

    $after = EmailDirectory::query()->orderBy('id')->get();
    expect($after->map(fn($item) => [
        'id' => $item->id,
        'updated_at' => $item->updated_at->toDateTimeString(),
        'synced_at' => $item->synced_at->toDateTimeString(),
        'sync_batch' => $item->sync_batch,
        'encrypted_email' => $item->getRawOriginal('email'),
        'encrypted_nik' => $item->getRawOriginal('nik'),
    ])->all())->toBe($baseline);
    expect(DB::table('email_directory_sync_states')
        ->where('name', 'email_directory')->value('last_success_at'))
        ->not->toBe($checkpoint);
    expect(app(EmailDirectoryService::class)->available())->toBeTrue();
});

it('updates only changed employee data and inserts new rows without rewriting other employees', function () {
    $sync = app(EmailDirectorySync::class);
    $original = incrementalDirectoryRowsV620();
    $sync->import($original);
    $before = EmailDirectory::query()->orderBy('id')->get();
    $untouched = $before[1];
    $cipher = $untouched->getRawOriginal('email');
    $batch = $untouched->sync_batch;
    $timestamp = $untouched->updated_at->toDateTimeString();

    $this->travel(1)->hour();
    $changed = incrementalDirectoryRowsV620(3);
    $changed[1][2] = 'baru@subang.go.id';
    expect($sync->import($changed))->toBe(3);
    expect($sync->stats())->toBe([
        'created' => 1, 'updated' => 1, 'unchanged' => 1, 'deleted' => 0,
    ]);
    expect(EmailDirectory::count())->toBe(3);
    expect($before[0]->fresh()->email)->toBe('baru@subang.go.id');
    expect($untouched->fresh()->getRawOriginal('email'))->toBe($cipher);
    expect($untouched->fresh()->sync_batch)->toBe($batch);
    expect($untouched->fresh()->updated_at->toDateTimeString())->toBe($timestamp);
});

it('removes a missing row while leaving retained rows untouched', function () {
    $sync = app(EmailDirectorySync::class);
    $rows = incrementalDirectoryRowsV620(3);
    $sync->import($rows);
    $before = EmailDirectory::query()->where('nama', 'Pegawai 1')->firstOrFail();
    $batch = $before->sync_batch;
    $this->travel(1)->hour();
    array_pop($rows);
    expect($sync->import($rows))->toBe(2);
    expect($sync->stats())->toBe([
        'created' => 0, 'updated' => 0, 'unchanged' => 2, 'deleted' => 1,
    ]);
    expect(EmailDirectory::count())->toBe(2);
    expect(EmailDirectory::where('nama', 'Pegawai 3')->exists())->toBeFalse();
    expect($before->fresh()->sync_batch)->toBe($batch);
});

it('rejects suspicious mass deletion and atomically restores every pending update', function () {
    $sync = app(EmailDirectorySync::class);
    $original = incrementalDirectoryRowsV620(40);
    $sync->import($original);
    $before = EmailDirectory::query()->where('nama', 'Pegawai 1')->firstOrFail();
    $batch = $before->sync_batch;
    $checkpoint = DB::table('email_directory_sync_states')
        ->where('name', 'email_directory')->value('last_success_at');
    $this->travel(1)->hour();
    $truncated = array_slice($original, 0, 12);
    $truncated[1][2] = 'semestinya.rollback@subang.go.id';
    expect(fn() => $sync->import($truncated))
        ->toThrow(\RuntimeException::class, 'Sinkronisasi ditolak');
    expect(EmailDirectory::count())->toBe(40);
    expect($before->fresh()->email)->toBe('pegawai1@subang.go.id');
    expect($before->fresh()->sync_batch)->toBe($batch);
    expect(DB::table('email_directory_sync_states')
        ->where('name', 'email_directory')->value('last_success_at'))
        ->toBe($checkpoint);
});

it('preserves the last good snapshot on invalid records or a header-only spreadsheet', function () {
    $sync = app(EmailDirectorySync::class);
    $rows = incrementalDirectoryRowsV620(2);
    $sync->import($rows);
    $checkpoint = DB::table('email_directory_sync_states')
        ->where('name', 'email_directory')->value('last_success_at');
    $rows[1][0] = '123';
    expect(fn() => $sync->import($rows))->toThrow(\RuntimeException::class, '16 digit');
    expect(fn() => $sync->import([incrementalDirectoryRowsV620()[0]]))
        ->toThrow(\RuntimeException::class);
    expect(EmailDirectory::count())->toBe(2);
    expect(DB::table('email_directory_sync_states')
        ->where('name', 'email_directory')->value('last_success_at'))->toBe($checkpoint);
});
