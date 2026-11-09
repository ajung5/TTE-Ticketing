<?php

use Illuminate\Support\Facades\Route;

it('does not register the discontinued Zoho webhook endpoint', function () {
    expect(Route::has('tickets.zoho'))->toBeFalse();
    $this->postJson('/api/tickets/zoho', [
        'submission_id' => 'legacy-disabled-test',
        'nama_user' => 'Pengujian Integrasi Nonaktif',
        'no_wa' => '081234567890',
        'layanan' => 'Pendaftaran'
    ], ['X-Zoho-Webhook-Secret' => str_repeat('X', 32)])->assertNotFound();
});
