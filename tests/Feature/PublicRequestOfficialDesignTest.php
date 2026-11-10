<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
