<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menyediakan tombol kontak pengelola email beserta asset native WhatsApp pada form publik', function () {
    config()->set('email_directory.admin_contact_url',
        'https://wa.me/6281212345678?text=Halo%20Pengelola%20Email');

    $this->get(route('public.form'))
        ->assertOk()
        ->assertSee('Hubungi Pengelola via WhatsApp')
        ->assertSee('data-email-admin-wa="1"', false)
        ->assertSee('https://wa.me/6281212345678?text=Halo%20Pengelola%20Email', false)
        ->assertSee('public-email-admin-whatsapp.js')
        ->assertSee('?v=65');
});

it('tidak menampilkan link WhatsApp saat kontak pengelola email belum tersedia', function () {
    config()->set('email_directory.admin_contact_url', null);
    $this->get(route('public.form'))
        ->assertOk()
        ->assertDontSee('data-email-admin-wa="1"', false)
        ->assertSee('Nomor WhatsApp pengelola email belum dikonfigurasi');
});

it('memiliki tautan native user-tap, fallback browser, dan tautan WhatsApp Web untuk desktop', function () {
    $js = file_get_contents(public_path('js/public-email-admin-whatsapp.js'));
    expect($js)
        ->toContain('whatsapp://send?phone=')
        ->toContain('https://web.whatsapp.com/send?phone=')
        ->toContain('Buka Aplikasi WhatsApp')
        ->toContain('Buka melalui browser')
        ->toContain('data-email-admin-wa')
        ->toContain('destination.hostname')
        ->toContain('waBrowserFallback')
        ->toContain('userAgentData?.mobile');
});
