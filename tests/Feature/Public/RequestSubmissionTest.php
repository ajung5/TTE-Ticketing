<?php

use App\Models\Ticket;
use App\Models\TicketEmailChange;
use App\Models\User;
use App\Services\EmailDirectorySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// Source: PublicRequestOfficialDesignTest.php
it(
    'menampilkan kop resmi Kabupaten Subang dan form progresif tanpa mengubah syarat persetujuan',
    function () {
        $response = $this->get(route('public.form'))
            ->assertOk()
            ->assertSee('PEMERINTAH DAERAH KABUPATEN SUBANG')
            ->assertSee('DINAS KOMUNIKASI DAN INFORMATIKA')
            ->assertSee('Jalan Mayjen Soetoyo Nomor 46')
            ->assertSee('Form Permohonan Layanan TTE')
            ->assertSee('Lambang Kabupaten Subang')
            ->assertSee('tte-request.css')
            ->assertSee('Data belum sesuai? Anda bisa memperbaikinya.')
            ->assertSee('id="signature-pad"', false)
            ->assertSee('Syarat dan Ketentuan')
            ->assertSee('Kami menghargai privasi Anda')
            ->assertSee('Dinas Komunikasi dan Informatika Kabupaten Subang')
            ->assertSee('Saya juga bersedia menerima pemberitahuan terkait status')
            ->assertSee('name="persetujuan"', false)
            ->assertSee('Saya setuju dengan Syarat dan Ketentuan yang berlaku.');

        expect(is_file(public_path('images/lambang-kabupaten-subang.png')))->toBeTrue();

        // TTE v69: format-independent semantic HTML assertions (attribute order/newlines do not matter).
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '~<section\b(?=[^>]*\sid="request-details")(?=[^>]*\shidden(?:\s|=|>))[^>]*>~is',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '~<input\b(?=[^>]*\sid="email_display")(?=[^>]*\stype="email")(?=[^>]*\sreadonly(?:\s|=|>))[^>]*>~is',
            $html,
        );
    },
);

// Source: TteDuplicateAndAmsEmailV61Test.php
function v61Rows(): array {
    return [
        ['NIK', 'NIP', 'Email Gov', 'NAMA', 'JABATAN', 'UNIT KERJA'],
        [
            '3273010101010001',
            '198901012020011001',
            'lama@subang.go.id',
            'Pegawai V61',
            'Analis',
            'Diskominfo',
        ],
    ];
}

function v61Payload(?string $token = null): array {
    return [
        'layanan' => 'Reset Passphrase',
        'nama_user' => 'Pegawai V61',
        'nik' => '3273010101010001',
        'nip' => '198901012020011001',
        'no_wa' => '85812341234',
        'opd' => 'Diskominfo',
        'jabatan' => 'Analis',
        'persetujuan' => '1',
        'submission_token' => $token ?? (string) Str::uuid(),
        'signature_data' =>
            'data:image/png;base64,' .
            base64_encode(file_get_contents(base_path('tests/Fixtures/signature-test.png'))),
    ];
}

it(
    'prevents a second active request, records only one repeat, and shows a generic notice',
    function () {
        Storage::fake('local');
        app(EmailDirectorySync::class)->import(v61Rows());
        $this->post(route('public.submit'), v61Payload())->assertRedirect(route('public.success'));
        $first = Ticket::firstOrFail();
        $second = v61Payload();
        $this->post(route('public.submit'), $second)->assertRedirect(route('public.success'));
        $this->get(route('public.success'))
            ->assertOk()
            ->assertSee('PERMOHONAN SEBELUMNYA MASIH AKTIF')
            ->assertDontSee($first->ticket_number);
        expect(Ticket::count())->toBe(1);
        expect($first->fresh()->duplicate_attempts)->toBe(1);
        expect($first->fresh()->last_duplicate_attempt_at)->not->toBeNull();
        expect(
            $first->fresh()->activities()->where('action', 'duplicate_request_blocked')->count(),
        )->toBe(1);
        $this->post(route('public.submit'), $second)->assertRedirect(route('public.success'));
        expect($first->fresh()->duplicate_attempts)->toBe(1);
        expect(Ticket::count())->toBe(1);
    },
);

it('checks active identity by NIP from the directory, not a browser edited NIP field', function () {
    Storage::fake('local');
    app(EmailDirectorySync::class)->import(v61Rows());
    $first = v61Payload();
    $first['nip'] = '199901019999991234';
    $this->post(route('public.submit'), $first)->assertRedirect(route('public.success'));
    $ticket = Ticket::firstOrFail();
    // NIP display remains editable as in the current form; the guard uses directory identity.
    expect($ticket->nip)->toBe('199901019999991234');
    $again = v61Payload();
    $again['nik'] = '198901012020011001'; // NIP-only lookup
    $this->post(route('public.submit'), $again)->assertRedirect(route('public.success'));
    expect(Ticket::count())->toBe(1);
    expect($ticket->fresh()->duplicate_attempts)->toBe(1);
});

it('permits a new application after the prior ticket is Done or Returned', function () {
    Storage::fake('local');
    app(EmailDirectorySync::class)->import(v61Rows());
    $this->post(route('public.submit'), v61Payload())->assertRedirect();
    $first = Ticket::firstOrFail();
    $first->update(['status' => 'Returned']);
    $this->post(route('public.submit'), v61Payload())->assertRedirect();
    expect(Ticket::count())->toBe(2);
    $second = Ticket::orderByDesc('id')->firstOrFail();
    $second->update(['status' => 'Done', 'completed_at' => now()]);
    $this->post(route('public.submit'), v61Payload())->assertRedirect();
    expect(Ticket::count())->toBe(3);
});

it(
    'records an AMS email correction by the owning verifier, not by admin or other verifiers',
    function () {
        Storage::fake('local');
        app(EmailDirectorySync::class)->import(v61Rows());
        $this->post(route('public.submit'), v61Payload())->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $owner = User::factory()->create(['role' => 'verifier']);
        $other = User::factory()->create(['role' => 'verifier']);
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = [
            'email' => 'baru@subang.go.id',
            'reason' => 'Email hasil pemeriksaan AMS berbeda dari direktori.',
            'ams_confirmed' => '1',
        ];
        $this->actingAs($admin)
            ->patch(route('tickets.email.update', $ticket), $payload)
            ->assertForbidden();
        $this->actingAs($other)
            ->patch(route('tickets.email.update', $ticket), $payload)
            ->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('tickets.email.update', $ticket), $payload)
            ->assertForbidden();
        $this->post(route('tickets.claim', $ticket))->assertRedirect();
        $this->patch(route('tickets.email.update', $ticket), $payload)->assertRedirect();
        $updated = $ticket->fresh();
        expect($updated->email)
            ->toBe('baru@subang.go.id')
            ->and($updated->directory_email)
            ->toBe('lama@subang.go.id')
            ->and($updated->email_corrected_by)
            ->toBe($owner->id);
        $audit = TicketEmailChange::firstOrFail();
        expect($audit->old_email)
            ->toBe('lama@subang.go.id')
            ->and($audit->new_email)
            ->toBe('baru@subang.go.id')
            ->and($audit->actor_id)
            ->toBe($owner->id);
        expect($updated->activities()->where('action', 'email_corrected_from_ams')->count())->toBe(
            1,
        );
    },
);

it('rejects non-government emails, unconfirmed corrections, and completed tickets', function () {
    Storage::fake('local');
    app(EmailDirectorySync::class)->import(v61Rows());
    $this->post(route('public.submit'), v61Payload())->assertRedirect();
    $ticket = Ticket::firstOrFail();
    $owner = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($owner)->post(route('tickets.claim', $ticket))->assertRedirect();
    $this->patch(route('tickets.email.update', $ticket), [
        'email' => 'gmail@example.com',
        'reason' => 'Pemeriksaan AMS menunjukkan email berbeda.',
        'ams_confirmed' => '1',
    ])->assertSessionHasErrors('email');
    $this->patch(route('tickets.email.update', $ticket), [
        'email' => 'baru@subang.go.id',
        'reason' => 'Pemeriksaan AMS menunjukkan email berbeda.',
    ])->assertSessionHasErrors('ams_confirmed');
    expect($ticket->fresh()->email)->toBe('lama@subang.go.id');
    $ticket->update(['status' => 'Done']);
    $this->patch(route('tickets.email.update', $ticket), [
        'email' => 'baru@subang.go.id',
        'reason' => 'Pemeriksaan AMS menunjukkan email berbeda.',
        'ams_confirmed' => '1',
    ])->assertStatus(409);
});

// Source: PublicEmailAdminWhatsAppNativeTest.php
it(
    'menyediakan tombol kontak pengelola email beserta asset native WhatsApp pada form publik',
    function () {
        config()->set(
            'email_directory.admin_contact_url',
            'https://wa.me/6281212345678?text=Halo%20Pengelola%20Email',
        );

        $this->get(route('public.form'))
            ->assertOk()
            ->assertSee('Hubungi Pengelola via WhatsApp')
            ->assertSee('data-email-admin-wa="1"', false)
            ->assertSee('https://wa.me/6281212345678?text=Halo%20Pengelola%20Email', false)
            ->assertSee('tte-request.js')
            ->assertSee(\App\Support\VersionedAsset::url('js/tte-request.js'), false);
    },
);

it('tidak menampilkan link WhatsApp saat kontak pengelola email belum tersedia', function () {
    config()->set('email_directory.admin_contact_url', null);
    $this->get(route('public.form'))
        ->assertOk()
        ->assertDontSee('data-email-admin-wa="1"', false)
        ->assertSee('Nomor WhatsApp pengelola email belum dikonfigurasi');
});

it(
    'memiliki tautan native user-tap, fallback browser, dan tautan WhatsApp Web untuk desktop',
    function () {
        $js = file_get_contents(public_path('js/tte-request.js'));
        expect($js)
            ->toContain('whatsapp://send?phone=')
            ->toContain('https://web.whatsapp.com/send?phone=')
            ->toContain('Buka Aplikasi WhatsApp')
            ->toContain('Buka melalui browser')
            ->toContain('data-email-admin-wa')
            ->toContain('destination.hostname')
            ->toContain('waBrowserFallback')
            ->toContain('userAgentData?.mobile');
    },
);
