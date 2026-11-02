<?php

use App\Models\EmailDirectory;
use App\Models\Ticket;
use App\Services\EmailDirectorySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function nipDirectoryRows(?string $nik = '3273010101010001', string $nip = '198901012020011001'): array
{
    return [
        ['NIK', 'NIP', 'Email Gov', 'NAMA', 'JABATAN', 'UNIT KERJA'],
        [$nik ?? '', $nip, 'pemohon@subang.go.id', 'Pemohon Contoh', 'Pranata Komputer', 'Diskominfo'],
    ];
}

function nipPublicForm(string $identifier): array
{
    return [
        'layanan' => 'Pendaftaran', 'nama_user' => 'Pemohon Contoh',
        'nik' => $identifier, 'nip' => '198901012020011001',
        'no_wa' => '85812341234', 'opd' => 'Diskominfo', 'jabatan' => 'Pranata Komputer',
        'persetujuan' => '1', 'submission_token' => (string) Str::uuid(),
        'signature_data' => 'data:image/png;base64.'.base64_encode(
            file_get_contents(base_path('tests/Fixtures/signature-test.png'))
        ),
    ];
}

it('shows a single identifier field that accepts 16 digit NIK or 18 digit NIP', function () {
    $this->get(route('public.form'))->assertOk()
        ->assertSee('NIK / NIP')
        ->assertSee('pattern="([0-9]{16}|[0-9]{18})"', false)
        ->assertSee('maxlength="18"', false)
        ->assertSee('NIP 18 digit');
});

it('can find an existing NIK record using its NIP and uses the authoritative NIK on submit', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    app(EmailDirectorySync::class)->import(nipDirectoryRows());

    $this->postJson(route('public.lookup-nik'), ['nik' => '198901012020011001'])
        ->assertOk()->assertJsonPath('status', 'found')
        ->assertJsonPath('email', 'pemohon@subang.go.id')
        ->assertJsonPath('nip', '198901012020011001');
    $this->post(route('public.submit'), nipPublicForm('198901012020011001'))
        ->assertRedirect(route('public.success'));
    $ticket = Ticket::firstOrFail();
    expect($ticket->nik)->toBe('3273010101010001');
    expect($ticket->nip)->toBe('198901012020011001');
    expect($ticket->email)->toBe('pemohon@subang.go.id');
    expect($ticket->no_wa)->toBe('6285812341234');
});

it('imports a directory record with NIP but without NIK, and submits with null NIK', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    app(EmailDirectorySync::class)->import(nipDirectoryRows(null));

    $person = EmailDirectory::firstOrFail();
    expect($person->nik)->toBeNull();
    $this->postJson(route('public.lookup-nik'), ['nik' => '198901012020011001'])
        ->assertOk()->assertJsonPath('status', 'found');
    $this->post(route('public.submit'), nipPublicForm('198901012020011001'))
        ->assertRedirect(route('public.success'));
    expect(Ticket::firstOrFail()->nik)->toBeNull();
});

it('rejects invalid identifiers and refuses NIP duplicates during sync', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(EmailDirectorySync::class)->import(nipDirectoryRows());
    foreach (['123', '12345678901234567', '1234567890123456789', 'NIP123456789012345', '1234567890123456x8'] as $invalid) {
        $this->postJson(route('public.lookup-nik'), ['nik' => $invalid])->assertUnprocessable();
        $this->post(route('public.submit'), nipPublicForm($invalid))->assertSessionHasErrors('nik');
    }
    $duplicate = nipDirectoryRows();
    $duplicate[] = ['3273010101010002', '198901012020011001', 'kedua@subang.go.id', 'Nama Kedua', '', ''];
    expect(fn () => app(EmailDirectorySync::class)->import($duplicate))
        ->toThrow(\RuntimeException::class, 'NIP duplikat');
    expect(EmailDirectory::count())->toBe(1);
});

it('rejects an unknown NIP even if the browser supplies a forged email address', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(EmailDirectorySync::class)->import(nipDirectoryRows());
    $this->postJson(route('public.lookup-nik'), ['nik' => '199901012025011001'])
        ->assertOk()->assertJsonPath('status', 'not_found');
    $data = nipPublicForm('199901012025011001');
    $data['email'] = 'penyerang@subang.go.id';
    $this->post(route('public.submit'), $data)->assertSessionHasErrors('nik');
    expect(Ticket::count())->toBe(0);
});
