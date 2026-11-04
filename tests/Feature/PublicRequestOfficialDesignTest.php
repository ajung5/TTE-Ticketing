<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menampilkan kop resmi Kabupaten Subang dan form progresif tanpa mengubah syarat persetujuan', function () {
    $this->get(route('public.form'))
        ->assertOk()
        ->assertSee('PEMERINTAH DAERAH KABUPATEN SUBANG')
        ->assertSee('DINAS KOMUNIKASI DAN INFORMATIKA')
        ->assertSee('Jalan Mayjen Soetoyo Nomor 46')
        ->assertSee('Form Permohonan Layanan TTE')
        ->assertSee('Lambang Kabupaten Subang')
        ->assertSee('public-request-official.css')
        ->assertSee('Data belum sesuai? Anda bisa memperbaikinya.')
        ->assertSee('id="request-details" hidden', false)
        ->assertSee('id="email_display" type="email"', false)
        ->assertSee('readonly', false)
        ->assertSee('id="signature-pad"', false)
        ->assertSee('Syarat dan Ketentuan')
        ->assertSee('Kami menghargai privasi Anda')
        ->assertSee('Dinas Komunikasi dan Informatika Kabupaten Subang')
        ->assertSee('Saya juga bersedia menerima pemberitahuan terkait status')
        ->assertSee('name="persetujuan"', false)
        ->assertSee('Saya setuju dengan Syarat dan Ketentuan yang berlaku.');

    expect(is_file(public_path('images/lambang-kabupaten-subang.png')))->toBeTrue();
});
