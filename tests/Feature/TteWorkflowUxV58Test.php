<?php

use App\Models\Ticket;
use App\Models\User;
use App\Models\WaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ux58Ticket(
    User $owner,
    string $status = 'In Progress',
    string $service = 'Reset Passphrase',
): Ticket {
    return Ticket::create([
        'nama_user' => 'Pemohon Uji UX',
        'no_wa' => '6281212345678',
        'layanan' => $service,
        'source' => 'direct',
        'status' => $status,
        'claimed_by' => $owner->id,
        'ticket_number' => 'TTE-UX58-' . uniqid(),
    ]);
}

it('offers one contextual next action with a backend-decided destination', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = ux58Ticket($verifier);
    $this->actingAs($verifier)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Tindakan Berikutnya')
        ->assertSee('Periksa akun di AMS BSrE')
        ->assertSee('href="#tte-ams-check"', false)
        ->assertSee('id="tte-ticket-workflow"', false);
    $this->post(route('tickets.verify-whatsapp', $ticket), [
        'activation_status' => 'not_activated',
    ])->assertSessionHasNoErrors();
    $this->get(route('tickets.show', $ticket))->assertSee('Kirim notifikasi sesuai AMS');
});

it('does not cancel a valid service confirmation when the same message is reopened', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = ux58Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $before = WaNotification::where('ticket_id', $ticket->id)
        ->where('event_type', 'activation_required')
        ->firstOrFail();
    expect($before->confirmed_at)->not->toBeNull();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    expect($before->fresh()->confirmed_at)->not->toBeNull();
});

it('invalidates a previous confirmation when the message content actually changes', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = ux58Ticket($verifier);
    $this->actingAs($verifier)
        ->post(route('tickets.verify-whatsapp', $ticket), [
            'activation_status' => 'not_activated',
        ])
        ->assertSessionHasNoErrors();
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.service.confirm', $ticket))->assertRedirect();
    $ticket->update(['nama_user' => 'Nama Baru Uji']);
    $this->post(route('tickets.whatsapp.service', $ticket))->assertRedirect();
    $notice = WaNotification::where('ticket_id', $ticket->id)
        ->where('event_type', 'activation_required')
        ->firstOrFail();
    expect($notice->confirmed_at)->toBeNull();
});

it('keeps final notification confirmed on reopening the identical content', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $ticket = ux58Ticket($verifier, 'Done');
    $this->actingAs($verifier)->post(route('tickets.whatsapp', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp.confirm', $ticket))->assertRedirect();
    $this->post(route('tickets.whatsapp', $ticket))->assertRedirect();
    expect(
        WaNotification::where('ticket_id', $ticket->id)
            ->where('event_type', 'completed')
            ->firstOrFail()->confirmed_at,
    )->not->toBeNull();
});

it('prioritizes own tickets and oldest unclaimed tickets, without exposing others', function () {
    $verifier = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $own = ux58Ticket($verifier);
    $foreign = ux58Ticket($other);
    $openOld = ux58Ticket($verifier, 'Open');
    $openOld->update(['claimed_by' => null, 'created_at' => now()->subDays(2)]);
    $openNew = ux58Ticket($verifier, 'Open');
    $openNew->update(['claimed_by' => null, 'created_at' => now()->subDay()]);
    $this->actingAs($verifier)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertSee($own->ticket_number)
        ->assertDontSee($foreign->ticket_number)
        ->assertDontSee($openOld->ticket_number);
    $result = $this->get(route('tickets.index', ['status' => 'Open']))->assertOk();
    expect(strpos($result->getContent(), $openOld->ticket_number))->toBeLessThan(
        strpos($result->getContent(), $openNew->ticket_number),
    );
});

it('displays read-only system health to admin and protects admin/verifier separation', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $verifier = User::factory()->create(['role' => 'verifier']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Kesehatan Sistem TTE')
        ->assertSee('Direktori Email')
        ->assertSee('Status konfigurasi, bukan bukti email terkirim');
    $this->get(route('admin.verifiers.index'))->assertOk()->assertSee('Kirim Reset Email');
    $this->actingAs($verifier)->get(route('admin.dashboard'))->assertForbidden();
});

it('uses synthetic data preview rather than a real employee record in preset editor', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.templates.index'))
        ->assertOk()
        ->assertSee('Pratinjau dengan data contoh')
        ->assertSee('tte-preset-preview.js')
        ->assertSee('data-tte-preset-preview');
    $js = file_get_contents(public_path('js/tte-preset-preview.js'));
    expect($js)->toContain('contoh@subang.go.id')->not->toContain('fetch(');
});
