<?php

use App\Models\Ticket;
use App\Models\User;
use App\Services\EmailDirectorySync;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function directRequestData(): array {
    return [
        'layanan' => 'Reset Passphrase', 'nama_user' => 'Pemohon Uji Integrasi',
        'nik' => '3273010101010001', 'nip' => '198901012020011001',
        'no_wa' => '081212345678', 'opd' => 'Diskominfo', 'jabatan' => 'Pranata Komputer',
        'signature_data' => 'data:image/png;base64,'.base64_encode(file_get_contents(base_path('tests/Fixtures/signature-test.png'))),
        'persetujuan' => '1', 'submission_token' => '4b8f5c10-f06e-4f5c-8a03-0a9260210b11',
    ];
}

it('accepts public direct submission and encrypts NIK', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    app(EmailDirectorySync::class)->import([['NIK','Email Gov'],['3273010101010001','penguji@subang.go.id']]);
    $this->get(route('public.form'))->assertOk();
    $this->post(route('public.submit'), directRequestData())->assertRedirect(route('public.success'));
    $ticket = Ticket::where('source','direct')->firstOrFail();
    expect($ticket->status)->toBe('Open');
    expect($ticket->email)->toBe('penguji@subang.go.id');
    expect($ticket->no_wa)->toBe('6281212345678');
    expect($ticket->getRawOriginal('nik'))->not->toBe('3273010101010001');
    $this->post(route('public.submit'), directRequestData())->assertRedirect(route('public.success'));
    expect(Ticket::where('source','direct')->count())->toBe(1);
});

it('rejects NIK absent from directory', function () {
    app(EmailDirectorySync::class)->import([['NIK','Email Gov'],['3273010101010001','penguji@subang.go.id']]);
    $data = directRequestData(); $data['nik'] = '123';
    $this->post(route('public.submit'), $data)->assertSessionHasErrors('nik');
});

it('selects WA-unverified notice, then renewal notice when status changes', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Test', 'no_wa' => '6281212345678', 'layanan' => 'Pembaruan',
        'status' => 'In Progress', 'source' => 'direct', 'claimed_by' => $verifier->id,
    ]);
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.verify-whatsapp', $ticket), [
        'certificate_status' => 'active', 'wa_verification_status' => 'unverified',
    ])->assertRedirect();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))->assertSee('Nomor WA Belum Terverifikasi');
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.verify-whatsapp', $ticket), [
        'certificate_status' => 'active', 'wa_verification_status' => 'verified',
    ])->assertRedirect();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))->assertSee('Pembaruan Sertifikat');
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
});

it('allows admin template variables while masking the NIK', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $template = \App\Models\WaMessageTemplate::where('service_code', '*')->where('event_type', 'unverified')->firstOrFail();
    $this->actingAs($admin)->put(route('admin.templates.update', $template), [
        'body' => 'Yth {nama_pemohon} ({nomor_tiket}). Email {email}, NIK {nik}, jabatan {jabatan}, OPD {opd}. Petugas {nama_verifikator}. Nomor belum terverifikasi di AMS BSrE.',
    ])->assertSessionHas('success');
    $ticket = Ticket::create([
        'nama_user' => 'Coba Pemohon', 'nik' => '3273010101010001', 'email' => 'pemohon@subang.go.id',
        'jabatan' => 'Analis', 'opd' => 'Diskominfo', 'no_wa' => '6281212345678',
        'layanan' => 'Pembaruan', 'status' => 'In Progress', 'source' => 'direct',
    ]);
    $message = app(\App\Services\WhatsAppMessage::class)->forServiceTicket($ticket, 'unverified');
    expect($message)->toContain('pemohon@subang.go.id')->toContain('Analis');
    expect($message)->toContain(str_repeat('•',12).'0001')->not->toContain('3273010101010001');
});

it('denies non-admin editing message templates', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $template = \App\Models\WaMessageTemplate::where('service_code', '*')->where('event_type', 'unverified')->firstOrFail();
    $this->actingAs($verifier)->put(route('admin.templates.update', $template), [
        'body' => str_repeat('Pesan contoh untuk pengujian. ',3),
    ])->assertForbidden();
});

it('shows completion notice for old tickets without requiring new AMS check', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Lama', 'no_wa' => '6281212345678', 'layanan' => 'Pendaftaran',
        'status' => 'Done', 'source' => 'zoho', 'claimed_by' => $verifier->id,
        'completed_at' => now(),
    ]);
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))->assertOk()->assertSee('completed-wa-message');
    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertRedirect();
});
