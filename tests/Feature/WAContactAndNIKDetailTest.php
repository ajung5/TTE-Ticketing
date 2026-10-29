<?php

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the configured WhatsApp email administrator contact on the public modal', function () {
    config()->set('email_directory.admin_contact_url',
        'https://wa.me/6281212345678?text=Halo%20Pengelola%20Email');

    $this->get(route('public.form'))
        ->assertOk()
        ->assertSee('Hubungi Pengelola via WhatsApp')
        ->assertSee('https://wa.me/6281212345678?text=Halo%20Pengelola%20Email', false)
        ->assertDontSee('Nomor WhatsApp pengelola email belum dikonfigurasi');
});

it('shows full NIK only to the assigned verifier on the ticket detail', function () {
    $owner = User::factory()->create(['role' => 'verifier']);
    $other = User::factory()->create(['role' => 'verifier']);
    $ticket = Ticket::create([
        'nama_user' => 'Pemohon Simulasi',
        'nik' => '3273010101010001',
        'no_wa' => '6281212345678',
        'layanan' => 'Pendaftaran',
        'status' => 'Open',
        'source' => 'direct',
    ]);

    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee($ticket->masked_nik)
        ->assertDontSee('3273010101010001');

    $this->actingAs($owner)->post(route('tickets.claim', $ticket))->assertRedirect();
    $this->actingAs($owner)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('3273010101010001')
        ->assertDontSee('(tersamarkan)');

    $this->actingAs($other)->get(route('tickets.show', $ticket))->assertForbidden();
});
