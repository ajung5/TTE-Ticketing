<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaMessageTemplate;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function v615CreateVerifierTicket(User $verifier): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Contoh',
        'no_wa' => '6281234567890',
        'layanan' => 'Reset Passphrase',
        'status' => 'In Progress',
        'source' => 'direct',
        'claimed_by' => $verifier->id,
    ]);
}

function v615Preset(): WaMessageTemplate {
    return WaMessageTemplate::query()
        ->where('event_type', 'reset')
        ->where('service_code', 'Reset Passphrase')
        ->firstOrFail();
}

it('lets admin save a WA preset with initials instead of a full verifier name', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create([
        'role' => 'verifier',
        'name' => 'Nama Panjang Petugas',
        'initials' => 'K.A. 01',
    ]);
    $template = v615Preset();
    $body =
        'Yth. {nama_pemohon}, permohonan {layanan} pada tiket {nomor_tiket} ' .
        'ditindaklanjuti petugas {inisial_verifikator}. Jangan bagikan OTP.';

    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), ['body' => $body])
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHasNoErrors();

    expect($template->fresh()->body)->toBe($body);
    $rendered = app(WhatsAppMessage::class)->forServiceTicket(
        v615CreateVerifierTicket($verifier),
        'reset',
    );
    expect($rendered)->toContain('K.A. 01')->not->toContain('Nama Panjang Petugas');
});

it('keeps full verifier names and rejects WA presets lacking both signatures', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $template = v615Preset();
    $withName =
        'Yth. {nama_pemohon}, tiket {nomor_tiket}. ' .
        'Terima kasih, {nama_verifikator}. Permohonan Anda sedang ditindaklanjuti.';
    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), ['body' => $withName])
        ->assertSessionHasNoErrors();
    $withoutSignature =
        'Yth. {nama_pemohon}, tiket {nomor_tiket}. ' .
        'Permohonan Anda sedang ditindaklanjuti sesuai pemeriksaan AMS BSrE.';
    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), ['body' => $withoutSignature])
        ->assertSessionHasErrors('body');
});

it('falls back to verifier name when initials are not configured', function () {
    $verifier = User::factory()->create([
        'role' => 'verifier',
        'name' => 'Petugas Pengganti',
        'initials' => null,
    ]);
    v615Preset()->update([
        'body' => 'Yth. {nama_pemohon}, tiket {nomor_tiket}; ditangani oleh {inisial_verifikator}.',
    ]);
    $rendered = app(WhatsAppMessage::class)->forServiceTicket(
        v615CreateVerifierTicket($verifier),
        'reset',
    );
    expect($rendered)->toContain('Petugas Pengganti')->not->toContain('oleh -.');
});

it('accepts freely chosen readable initials without forcing uppercase', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $password = 'ContohPassword!2026';
    $this->actingAs($admin)
        ->post(route('admin.verifiers.store'), [
            'name' => 'Petugas TTE Subang',
            'email' => 'initials-v615@example.test',
            'initials' => 'Aj. 01 / TTE',
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertRedirect(route('admin.verifiers.index'))
        ->assertSessionHasNoErrors();
    $verifier = User::where('email', 'initials-v615@example.test')->firstOrFail();
    expect($verifier->initials)->toBe('Aj. 01 / TTE');

    $this->actingAs($admin)
        ->put(route('admin.verifiers.update', $verifier), [
            'name' => $verifier->name,
            'email' => $verifier->email,
            'initials' => 'sandi.01',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('admin.verifiers.index'))
        ->assertSessionHasNoErrors();
    expect($verifier->fresh()->initials)->toBe('sandi.01');
});

it('rejects initials exceeding storage length or containing control characters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    foreach (['13-char-limit', "A\nB"] as $value) {
        $this->actingAs($admin)
            ->put(route('admin.verifiers.update', $verifier), [
                'name' => $verifier->name,
                'email' => $verifier->email,
                'initials' => $value,
            ])
            ->assertSessionHasErrors('initials');
    }
});

it('does not allow verifiers to edit WA presets or other verifier accounts', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($verifier)
        ->put(route('admin.templates.update', v615Preset()), [
            'body' => 'Yth. {nama_pemohon}, tiket {nomor_tiket}. Petugas {inisial_verifikator}.',
        ])
        ->assertForbidden();
    $this->actingAs($verifier)
        ->put(route('admin.verifiers.update', $other), [
            'name' => 'Berubah',
            'email' => $other->email,
            'initials' => 'SA',
        ])
        ->assertForbidden();
});
