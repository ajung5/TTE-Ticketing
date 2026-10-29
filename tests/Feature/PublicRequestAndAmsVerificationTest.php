<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function directRequestData(): array
{
    return [
        'layanan' => 'Reset Passphrase', 'nama_user' => 'Pemohon Uji Integrasi',
        'nik' => '3273010101010001', 'nip' => '198901012020011001',
        'email' => 'penguji@subang.go.id', 'no_wa' => '081212345678',
        'opd' => 'Diskominfo', 'jabatan' => 'Pranata Komputer',
        'persetujuan' => '1', 'submission_token' => '4b8f5c10-f06e-4f5c-8a03-0a9260210b11',
    ];
}

it('accepts public direct submission without login and encrypts NIK', function () {
    $this->get(route('public.form'))->assertOk()->assertSee('Permohonan Layanan TTE');
    $this->post(route('public.submit'), directRequestData())->assertRedirect(route('public.success'));
    $ticket = Ticket::where('source', 'direct')->firstOrFail();
    expect($ticket->status)->toBe('Open');
    expect($ticket->no_wa)->toBe('6281212345678');
    expect($ticket->nik)->toBe('3273010101010001');
    expect($ticket->getRawOriginal('nik'))->not->toBe('3273010101010001');
    expect($ticket->masked_nik)->toBe(str_repeat('•', 12).'0001');
    $this->get(route('public.success'))->assertOk()->assertSee($ticket->ticket_number);
    $this->post(route('public.submit'), directRequestData())->assertRedirect(route('public.success'));
    expect(Ticket::where('source', 'direct')->count())->toBe(1);
});

it('rejects incomplete submissions and non-subang emails', function () {
    $data = directRequestData();
    $data['nik'] = '123';
    $data['email'] = 'test@example.com';
    $this->post(route('public.submit'), $data)->assertSessionHasErrors(['nik','email']);
    expect(Ticket::count())->toBe(0);
});

it('blocks normal WhatsApp and completion until AMS verification, allowing unverified notice', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Test', 'no_wa' => '6281212345678', 'layanan' => 'Pembaruan',
        'status' => 'In Progress', 'source' => 'direct', 'claimed_by' => $verifier->id,
    ]);
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket), ['resolution_note' => 'Penanganan telah diselesaikan.'])->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.whatsapp.followup', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'unverified',
        'wa_verification_note' => 'Diperiksa pada profil AMS, status WA belum terverifikasi.',
    ])->assertRedirect();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))->assertOk()->assertSee('Nomor WhatsApp Belum Terverifikasi');
    $this->actingAs($verifier)->post(route('tickets.whatsapp.unverified', $ticket))->assertRedirect();
    $this->assertDatabaseHas('wa_notifications', ['ticket_id' => $ticket->id, 'event_type' => 'unverified']);
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket), ['resolution_note' => 'Penanganan telah diselesaikan.'])->assertStatus(409);
    $this->actingAs($verifier)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
        'wa_verification_note' => 'AMS menunjukkan status nomor WhatsApp sudah terverifikasi.',
    ])->assertRedirect();
    $this->actingAs($verifier)->post(route('tickets.complete', $ticket), ['resolution_note' => 'Penanganan telah diselesaikan.'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
});

it('allows admin to use form variables in WhatsApp template but masks the NIK', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $template = \App\Models\WaMessageTemplate::where('service_code', 'Pembaruan')->where('event_type', 'unverified')->firstOrFail();
    $this->actingAs($admin)->put(route('admin.templates.update', $template), [
        'body' => 'Yth {nama_pemohon} ({nomor_tiket}). Email {email}, NIK {nik}, jabatan {jabatan}, OPD {opd}. Petugas {nama_verifikator}. Nomor belum terverifikasi di AMS BSrE.',
    ])->assertSessionHas('success');
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Coba Pemohon', 'nik' => '3273010101010001', 'email' => 'pemohon@subang.go.id',
        'jabatan' => 'Analis', 'opd' => 'Diskominfo', 'no_wa' => '6281212345678',
        'layanan' => 'Pembaruan', 'status' => 'In Progress', 'source' => 'direct',
        'claimed_by' => $verifier->id, 'wa_verification_status' => 'unverified',
    ]);
    $ticket->load('claimant');
    $message = app(\App\Services\WhatsAppMessage::class)->forUnverifiedTicket($ticket);
    expect($message)->toContain('pemohon@subang.go.id');
    expect($message)->toContain('Analis');
    expect($message)->toContain(str_repeat('•', 12).'0001');
    expect($message)->not->toContain('3273010101010001');
});

it('denies non-admin editing message templates', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $template = \App\Models\WaMessageTemplate::firstOrFail();
    $this->actingAs($verifier)->put(route('admin.templates.update', $template), [
        'body' => str_repeat('Pesan contoh untuk pengujian. ', 3),
    ])->assertForbidden();
});

it('allows legacy completed tickets to record AMS verification before notifying', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Lama', 'no_wa' => '6281212345678', 'layanan' => 'Pendaftaran',
        'status' => 'Done', 'source' => 'zoho', 'claimed_by' => $verifier->id,
        'completed_at' => now(), 'resolution_note' => 'Pekerjaan terdahulu selesai.',
    ]);
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('Verifikasi Nomor di AMS BSrE');
    $this->actingAs($verifier)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified',
        'wa_verification_note' => 'Nomor lama diperiksa ulang di AMS dan sudah terverifikasi.',
    ])->assertRedirect();
    $this->actingAs($verifier)->get(route('tickets.show', $ticket))
        ->assertOk()->assertSee('completed-wa-message');
});
