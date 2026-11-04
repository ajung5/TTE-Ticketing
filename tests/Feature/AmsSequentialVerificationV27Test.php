<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// AMS v29: khusus HTTP test, lewati middleware CSRF; auth/session tetap diuji.
// HTTP tests Laravel biasanya melewati CSRF otomatis. Mesin ini masih menghasilkan
// 419 pada semua POST; jangan mengecualikan rute CSRF di konfigurasi produksi.
beforeEach(function (): void {
    // Hindari pengujian mengubah database sungguhan akibat config cache/.env.
    if (config('database.default') !== 'sqlite' ||
        config('database.connections.sqlite.database') !== ':memory:') {
        throw new \RuntimeException(
            'TEST DIHENTIKAN: database pengujian harus SQLite :memory:. ' .
            'Periksa phpunit.xml, .env.testing, dan bootstrap/cache/config.php.'
        );
    }

    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
});

function amsV27Ticket(User $owner, string $service = 'Pembaruan'): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon AMS V27', 'no_wa' => '6281212345678',
        'email' => 'uji@example.test', 'layanan' => $service,
        'source' => 'direct', 'status' => 'In Progress', 'claimed_by' => $owner->id,
    ]);
}

it('requires account activation before WA and issuance, without inferring activation from verified WA', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV27Ticket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'wa_verification_status' => 'verified', 'certificate_issuance_status' => 'issued',
        'certificate_validity_status' => 'active',
    ])->assertRedirect()->assertSessionHasErrors('activation_status');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued',
    ])->assertRedirect()->assertSessionHasErrors('certificate_validity_status');
    expect($ticket->fresh()->wa_checked_at)->toBeNull();
});

it('keeps later stages unexamined when the account is not activated', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV27Ticket($owner, 'Reset Passphrase');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $record = $ticket->fresh();
    expect($record->wa_verification_status)->toBe('unchecked');
    expect($record->certificate_issuance_status)->toBeNull();
    expect($record->certificate_validity_status)->toBeNull();
    expect($record->recommended_service)->toBeNull();
    $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Aktivasi Akun Diperlukan')
        ->assertDontSee('Kembalikan Permohonan');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
});

it('prioritizes WA verification and never completes an expired renewal certificate', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV27Ticket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'unverified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'expired',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Nomor WA Belum Terverifikasi');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'expired',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Sertifikat kedaluwarsa');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
});

it('routes an expired reset certificate to renewal, not completion', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV27Ticket($owner, 'Reset Passphrase');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'expired',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBe('Pembaruan');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.return-for-correction', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Returned');
});

it('shows AMS summary to admins but forbids admins to edit verifier results', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = amsV27Ticket($owner);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'expired',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('SE Kedaluwarsa');
    $this->get(route('admin.reports.tickets.index'))->assertOk()
        ->assertSee('SE Kedaluwarsa')->assertSee('Aktivasi');
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
    ])->assertForbidden();
});

it('continues set passphrase on the same ticket despite unverified WhatsApp and finishes after recheck', function (string $service) {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV27Ticket($owner, $service);
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'unverified',
        'certificate_issuance_status' => 'not_issued',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBeNull();
    $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('SE Belum Terbit')
        ->assertSee('Nomor WA juga belum terverifikasi')->assertDontSee('Kembalikan Permohonan');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertOk()
        ->assertSee('Siap Ditandai Selesai')->assertDontSee('Kembalikan Permohonan');
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
    expect(\App\Models\TicketActivity::query()->where('ticket_id', $ticket->id)
        ->where('action', 'ams_checked')->where('note', 'like', 'Pemeriksaan ulang%')->exists())->toBeTrue();
})->with(['Pendaftaran', 'Reset Passphrase', 'Pembaruan']);

it('does not misclassify later issuance as wrong registration after account activation', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV27Ticket($owner, 'Pendaftaran');
    $this->actingAs($owner)->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Pendaftaran Diproses');
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => 'active',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($ticket->fresh()->recommended_service)->toBeNull();
    $this->get(route('tickets.show', $ticket))->assertDontSee('Perbaikan Jenis Permohonan');
    $this->post(route('tickets.complete', $ticket))->assertRedirect();
    expect($ticket->fresh()->status)->toBe('Done');
});

it('does not infer an active certificate from historical issued status', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $ticket = amsV27Ticket($owner, 'Reset Passphrase');
    $ticket->forceFill([
        'activation_status' => 'activated', 'wa_verification_status' => 'verified',
        'certificate_issuance_status' => 'issued', 'certificate_validity_status' => null,
        'wa_checked_at' => now(), 'certificate_status' => 'active',
    ])->save();
    $this->actingAs($owner)->get(route('tickets.show', $ticket))->assertOk()
        ->assertSee('Masa berlaku belum diperiksa');
    $this->post(route('tickets.complete', $ticket))->assertStatus(409);
});
