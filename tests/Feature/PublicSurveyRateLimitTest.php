<?php

use App\Models\ServiceSurveyResponse;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->freezeTime();
    // Cache khusus pengujian; tidak membersihkan cache aplikasi/database.
    Cache::store('array')->flush();
});

function surveyRateTicket(): Ticket
{
    return Ticket::create([
        'ticket_number' => 'TTE-RATE-' . Str::random(16),
        'nama_user' => 'Pemohon Survei Uji',
        'no_wa' => '6281212345678',
        'layanan' => 'Reset Passphrase',
        'status' => 'Done',
        'source' => 'direct',
    ]);
}

function surveyRateUrl(Ticket $ticket): string
{
    return URL::temporarySignedRoute('public.survey.show', now()->addDays(30), ['ticket' => $ticket->id]);
}

function surveyRatePayload(): array
{
    return [
        'respondent_name' => 'Responden Survei Uji',
        'overall_rating' => 5,
        'staff_rating' => 4,
        'clarity_rating' => 5,
        'speed_rating' => 4,
        'feedback' => 'Pelayanan membantu.',
    ];
}

it('keeps page views separate from survey submission attempts', function () {
    $ticket = surveyRateTicket();
    $url = surveyRateUrl($ticket);
    for ($i = 0; $i < 20; $i++) {
        $this->get($url)->assertOk();
    }
    $this->get($url)->assertStatus(429)->assertHeader('Retry-After')
        ->assertSee('Akses Survei Dibatasi Sementara');
    $this->post($url, surveyRatePayload())->assertRedirect(route('public.survey.thanks'));
    $this->get(route('public.survey.thanks'))->assertOk();
    expect(ServiceSurveyResponse::where('ticket_id', $ticket->id)->count())->toBe(1);
});

it('keeps survey quotas independent from an exhausted numeric throttle on another route', function () {
    Route::post('/test-legacy-throttle', fn () => response('OK'))->middleware(['web', 'throttle:5,10']);
    for ($i = 0; $i < 5; $i++) {
        $this->post('/test-legacy-throttle')->assertOk();
    }
    $this->post('/test-legacy-throttle')->assertStatus(429);
    $url = surveyRateUrl(surveyRateTicket());
    $this->get($url)->assertOk();
    $this->post($url, surveyRatePayload())->assertRedirect(route('public.survey.thanks'));
});

it('allows different survey tickets on the same office IP without sharing a submission quota', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);
    for ($i = 0; $i < 8; $i++) {
        $ticket = surveyRateTicket();
        $url = surveyRateUrl($ticket);
        $this->get($url)->assertOk();
        $this->post($url, surveyRatePayload())->assertRedirect(route('public.survey.thanks'));
    }
    expect(ServiceSurveyResponse::count())->toBe(8);
});

it('preserves form input on throttling and accepts a retry after the limit expires', function () {
    $ticket = surveyRateTicket();
    $url = surveyRateUrl($ticket);
    for ($i = 0; $i < 5; $i++) {
        $this->from($url)->post($url, [])->assertSessionHasErrors('respondent_name');
    }
    $response = $this->post($url, surveyRatePayload() + ['password' => 'must-not-be-flashed']);
    $response->assertStatus(429)->assertHeader('Retry-After')
        ->assertSee('Pengiriman Survei Dibatasi Sementara')
        ->assertSee('Pengiriman kali ini belum diproses')
        ->assertSee($url)
        ->assertSessionHas('_old_input.respondent_name', 'Responden Survei Uji')
        ->assertSessionHas('_old_input.overall_rating', 5)
        ->assertSessionMissing('_old_input.password');
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)->toBeLessThanOrEqual(600);
    expect(ServiceSurveyResponse::count())->toBe(0);
    $this->get($url)->assertOk()->assertSee('Responden Survei Uji');

    $this->travel(601)->seconds();
    $this->post($url, surveyRatePayload())->assertRedirect(route('public.survey.thanks'));
    $this->post($url, surveyRatePayload())->assertRedirect(route('public.survey.thanks'));
    expect(ServiceSurveyResponse::where('ticket_id', $ticket->id)->count())->toBe(1);
    $this->get($url)->assertOk()->assertSee('Survei untuk tiket ini sudah diisi');
});

it('enforces the same ticket limit across IP changes and returns useful JSON retry metadata', function () {
    $url = surveyRateUrl(surveyRateTicket());
    for ($i = 0; $i < 5; $i++) {
        $this->postJson($url, [])->assertUnprocessable();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30']);
    $response = $this->postJson($url, surveyRatePayload());
    $response->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['message', 'retry_after']);
    expect((int) $response->json('retry_after'))->toBeGreaterThan(0)
        ->and((int) $response->json('retry_after'))->toBe((int) $response->headers->get('Retry-After'));
    $this->post(surveyRateUrl(surveyRateTicket()), surveyRatePayload())
        ->assertRedirect(route('public.survey.thanks'));
});

it('rejects invalid signed URLs without spending the valid ticket quota', function () {
    $ticket = surveyRateTicket();
    for ($i = 0; $i < 4; $i++) {
        $this->post('/survei/' . $ticket->id, surveyRatePayload())->assertForbidden();
    }
    $expired = URL::temporarySignedRoute('public.survey.show', now()->subMinute(), ['ticket' => $ticket->id]);
    $this->post($expired, surveyRatePayload())->assertForbidden();
    $this->assertDatabaseCount('service_survey_responses', 0);
    $this->post(surveyRateUrl($ticket), surveyRatePayload())->assertRedirect(route('public.survey.thanks'));
});
