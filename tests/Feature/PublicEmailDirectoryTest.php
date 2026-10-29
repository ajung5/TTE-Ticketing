<?php

use App\Models\EmailDirectory;
use App\Models\Ticket;
use App\Services\EmailDirectoryService;
use App\Services\EmailDirectorySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function directoryRows(string $email = 'pemohon@subang.go.id'): array
{
    return [
        ['No', 'NAMA', 'NIK', 'NIP', 'Email Gov', 'JABATAN', 'JENIS JABATAN', 'RUMPUN JABATAN', 'STATUS PEGAWAI', 'STATUS KEPEGAWAIAN', 'NAMA UNOR', 'Jenis Instansi', 'UPTD', 'UNIT KERJA', 'Status Pengguna', 'Status Sertifikat', 'Tanggal terbit', 'Tanggal berakhir'],
        ['1', 'Pemohon Contoh', '3273010101010001', '198901012020011001', $email, 'Analis', '', '', '', '', 'Sekretariat', '', '', 'Diskominfo', 'Aktif', '', '', ''],
    ];
}

function directFormWithSignature(): array
{
    return [
        'layanan' => 'Reset Passphrase', 'nama_user' => 'Pemohon Contoh',
        'nik' => '3273010101010001', 'nip' => '198901012020011001',
        'email' => 'palsu@subang.go.id', // must be ignored in favor of directory record
        'no_wa' => '081212345678', 'opd' => 'Diskominfo', 'jabatan' => 'Analis',
        'persetujuan' => '1', 'submission_token' => '4b8f5c10-f06e-4f5c-8a03-0a9260210b11',
        'signature_data' => 'data:image/png;base64,'.base64_encode(file_get_contents(base_path('tests/Fixtures/signature-test.png'))),
    ];
}

it('requires a synchronized email directory for NIK lookup', function () {
    $this->postJson(route('public.lookup-nik'), ['nik' => '3273010101010001'])
        ->assertStatus(503)->assertJsonPath('status', 'service_unavailable');
});

it('returns full immutable directory email and employee autofill for matching NIK', function () {
    app(EmailDirectorySync::class)->import(directoryRows());
    $this->postJson(route('public.lookup-nik'), ['nik' => '3273010101010001'])
        ->assertOk()->assertJsonPath('status', 'found')
        ->assertJsonPath('email', 'pemohon@subang.go.id')
        ->assertJsonPath('nama_user', 'Pemohon Contoh')
        ->assertJsonPath('nip', '198901012020011001')
        ->assertJsonPath('jabatan', 'Analis')
        ->assertJsonPath('opd', 'Diskominfo')
        ->assertDontSee('email_masked');
    $this->postJson(route('public.lookup-nik'), ['nik' => '3273010101010002'])
        ->assertOk()->assertJsonPath('status', 'not_found');
});

it('rejects empty usernames and allows re-synchronization without changing the last good data after an error', function () {
    $sync = app(EmailDirectorySync::class);
    $sync->import(directoryRows(''));
    $this->postJson(route('public.lookup-nik'), ['nik' => '3273010101010001'])
        ->assertOk()->assertJsonPath('status', 'not_found');
    $sync->import(directoryRows());
    $this->postJson(route('public.lookup-nik'), ['nik' => '3273010101010001'])
        ->assertOk()->assertJsonPath('status', 'found');
    try {
        $sync->import([directoryRows()[0], ['1', 'Contoh', '123', '', 'example@subang.go.id']]);
        $this->fail('Invalid NIK should throw.');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toContain('16 digit');
    }
    expect(EmailDirectory::count())->toBe(1);
});

it('creates a public ticket from authoritative directory email and stores private signature', function () {
    Storage::fake('local');
    app(EmailDirectorySync::class)->import(directoryRows());
    $data = directFormWithSignature();
    $this->post(route('public.submit'), $data)->assertRedirect(route('public.success'));
    $ticket = Ticket::where('source', 'direct')->firstOrFail();
    expect($ticket->email)->toBe('pemohon@subang.go.id');
    expect($ticket->consent_accepted_at)->not->toBeNull();
    expect($ticket->nik)->toBe('3273010101010001');
    expect($ticket->signature_path)->not->toBeNull();
    Storage::disk('local')->assertExists($ticket->signature_path);
    $verifier = \App\Models\User::factory()->create(['role' => 'verifier']);
    $other = \App\Models\User::factory()->create(['role' => 'verifier']);
    $this->actingAs($other)->get(route('tickets.signature', $ticket))->assertForbidden();
    $ticket->update(['claimed_by' => $verifier->id, 'status' => 'In Progress']);
    $this->actingAs($verifier)->get(route('tickets.signature', $ticket))->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($ticket->signature_path)->toStartWith('signatures/');
    $this->post(route('public.submit'), $data)->assertRedirect(route('public.success'));
    expect(Ticket::where('source', 'direct')->count())->toBe(1);
});

it('blocks direct submissions if NIK not present, username empty, signature missing, or consent missing', function () {
    app(EmailDirectorySync::class)->import(directoryRows(''));
    $this->post(route('public.submit'), directFormWithSignature())->assertSessionHasErrors('nik');
    app(EmailDirectorySync::class)->import(directoryRows());
    $this->post(route('public.submit'), array_diff_key(directFormWithSignature(), ['signature_data' => true]))
        ->assertSessionHasErrors('signature_data');
    $this->post(route('public.submit'), array_diff_key(directFormWithSignature(), ['persetujuan' => true]))
        ->assertSessionHasErrors('persetujuan');
    $data = directFormWithSignature(); $data['nik'] = '3273010101010002';
    $this->post(route('public.submit'), $data)->assertSessionHasErrors('nik');
    expect(Ticket::count())->toBe(0);
});

it('does not store plaintext NIK or email in directory columns', function () {
    app(EmailDirectorySync::class)->import(directoryRows());
    $person = app(EmailDirectoryService::class)->find('3273010101010001');
    expect($person)->not->toBeNull();
    expect($person->nik)->toBe('3273010101010001');
    expect($person->email)->toBe('pemohon@subang.go.id');
    expect($person->getRawOriginal('nik'))->not->toContain('3273010101010001');
    expect($person->getRawOriginal('email'))->not->toContain('pemohon@subang.go.id');
});

it('allows correcting directory name, NIP, jabatan and OPD while email stays server-authoritative', function () {
    Storage::fake('local');
    app(EmailDirectorySync::class)->import(directoryRows("ma'mun.adnan@subang.go.id"));
    $data = directFormWithSignature();
    $data['nama_user'] = 'Nama Koreksi Resmi';
    $data['nip'] = '199901012025011001';
    $data['jabatan'] = 'Pranata Komputer Ahli Pertama';
    $data['opd'] = 'Dinas Komunikasi dan Informatika';
    $data['email'] = 'alamat-palsu@subang.go.id';

    $this->post(route('public.submit'), $data)->assertRedirect(route('public.success'));
    $ticket = Ticket::where('source', 'direct')->firstOrFail();
    expect($ticket->nama_user)->toBe('Nama Koreksi Resmi');
    expect($ticket->nip)->toBe('199901012025011001');
    expect($ticket->jabatan)->toBe('Pranata Komputer Ahli Pertama');
    expect($ticket->opd)->toBe('Dinas Komunikasi dan Informatika');
    expect($ticket->email)->toBe("ma'mun.adnan@subang.go.id");
});

it('shows immutable email and only NIK and service before successful lookup', function () {
    $this->get(route('public.form'))->assertOk()
        ->assertSee('id="request-details" hidden', false)
        ->assertSee('id="email_display" type="email"', false)
        ->assertSee('readonly', false)
        ->assertSee('public-request-v5.css');
});
