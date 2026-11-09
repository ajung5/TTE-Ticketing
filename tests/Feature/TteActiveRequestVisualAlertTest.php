<?php

// TTE v6.1: visual warning is separate from a successful ticket submission.
it('shows an accessible red warning for active previous requests', function () {
    $this->withSession(['active_request_notice' => true])
        ->get(route('public.success'))
        ->assertOk()
        ->assertSee('PERMOHONAN SEBELUMNYA MASIH AKTIF')
        ->assertSee('tte-request-blocked--danger')
        ->assertSee('role="alert"', false)
        ->assertSee('css/tte-active-request-alert.css?v=61.2', false)
        ->assertDontSee('PERMOHONAN BERHASIL DIKIRIM')
        ->assertDontSee('public-ticket-number');
});

it('keeps the normal success appearance for a newly created ticket', function () {
    $this->withSession(['submission_success_ticket' => 'TTE-UAT-000001'])
        ->get(route('public.success'))
        ->assertOk()
        ->assertSee('PERMOHONAN BERHASIL DIKIRIM')
        ->assertSee('TTE-UAT-000001')
        ->assertDontSee('tte-request-blocked--danger');
});
