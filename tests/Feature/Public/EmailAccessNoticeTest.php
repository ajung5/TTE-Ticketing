<?php

it('menampilkan himbauan akses email dinas sebelum mulai permohonan', function () {
    $this->get(route('public.form'))
        ->assertOk()
        ->assertSeeInOrder(['id="email-access-notice"', 'Pastikan Email Dinas Dapat Diakses', 'Mulai Permohonan'], false)
        ->assertSee('href="https://mail.subang.go.id"', false)
        ->assertSee('Reset Passphrase, Pembaruan, atau Penerbitan Sertifikat Elektronik')
        ->assertSee('lupa username, atau lupa password')
        ->assertDontSee('Cek Akses Email Dinas');
});

it('menampilkan hyperlink nomor WhatsApp tanpa tombol pada banner dan mempertahankan popup', function () {
    $url = 'https://wa.me/6281212345678?text=Halo';
    config()->set('email_directory.admin_contact_phone', '081212345678');
    config()->set('email_directory.admin_contact_url', $url);
    $response = $this->get(route('public.form'))->assertOk();
    $html = $response->getContent();
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $banner = $xpath->query('//*[@id="email-access-notice"]')->item(0);
    expect($banner)->not->toBeNull();
    expect($xpath->query('.//button | .//a[contains(concat(" ", normalize-space(@class), " "), " btn ")]', $banner)->length)->toBe(0);
    expect($xpath->query('.//a[@href="'.$url.'" and normalize-space(.)="081212345678"]', $banner)->length)->toBe(1);
    expect($xpath->query('.//*[@data-email-admin-wa]', $banner)->length)->toBe(0);
    expect($xpath->query('//*[@id="email-help-modal"]//a[@data-email-admin-wa="1"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="email-help-trigger"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="email-modal-close"]')->length)->toBe(1);
});

it('menampilkan himbauan tanpa nomor fiktif jika kontak belum diatur', function () {
    config()->set('email_directory.admin_contact_phone', null);
    config()->set('email_directory.admin_contact_url', null);
    $this->get(route('public.form'))
        ->assertOk()
        ->assertSee('Pastikan Email Dinas Dapat Diakses')
        ->assertSee('Silakan hubungi helpdesk internal untuk informasi kontak pengelola email.')
        ->assertDontSee('data-email-admin-wa="1"', false);
});
