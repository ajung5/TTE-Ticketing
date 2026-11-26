<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('tte.turnstile', [
        'site_key' => 'test-site',
        'secret_key' => 'test-secret',
        'hostname' => 'tte-ticket.test',
    ]);
    Http::preventStrayRequests();
});

function captchaLoginPayload(User $user, string $token = 'test-token'): array {
    return [
        'email' => $user->email,
        'password' => 'TestPassword!2026',
        'cf-turnstile-response' => $token,
    ];
}

it('requires captcha before login and does not cache the form', function () {
    $user = User::factory()->create(['role' => 'verifier', 'password' => 'TestPassword!2026']);
    $response = $this->get(route('login'))->assertOk()
        ->assertSee('cf-turnstile')
        ->assertSee('data-action="tte_login"', false)
        ->assertSee('autocomplete="off"', false)
        ->assertDontSee('autocomplete="current-password"', false);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->post(route('login.submit'), [
        'email' => $user->email, 'password' => 'TestPassword!2026',
    ])->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
    Http::assertNothingSent();
});

it('authenticates only after siteverify succeeds with the expected hostname and action', function () {
    $user = User::factory()->create(['role' => 'verifier', 'password' => 'TestPassword!2026']);
    Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
        'success' => true, 'hostname' => 'tte-ticket.test', 'action' => 'tte_login',
    ])]);
    $this->post(route('login.submit'), captchaLoginPayload($user))
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    Http::assertSent(fn($request) => $request['response'] === 'test-token'
        && !isset($request['password']) && !isset($request['email']));
});

it('fails closed on rejected tokens, wrong action, wrong hostname and provider errors', function () {
    $user = User::factory()->create(['role' => 'verifier', 'password' => 'TestPassword!2026']);
    Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::sequence()
        ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']])
        ->push(['success' => true, 'hostname' => 'tte-ticket.test', 'action' => 'other'])
        ->push(['success' => true, 'hostname' => 'other.test', 'action' => 'tte_login'])
        ->push([], 503)]);
    foreach (range(1, 4) as $attempt) {
        $this->post(route('login.submit'), captchaLoginPayload($user, 'token-' . $attempt))
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }
});

it('does not allow login when the captcha secret is missing', function () {
    config()->set('tte.turnstile.secret_key', null);
    $user = User::factory()->create(['role' => 'verifier', 'password' => 'TestPassword!2026']);
    $this->post(route('login.submit'), captchaLoginPayload($user))
        ->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
    Http::assertNothingSent();
});
