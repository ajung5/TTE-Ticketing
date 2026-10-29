<?php

use App\Models\ServiceSurveyResponse;
use App\Models\Ticket;
use App\Models\User;
use App\Services\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function surveyTestTicket(array $attrs = []): Ticket {
    return Ticket::create(array_merge([
        'nama_user' => 'Pemohon Uji',
        'no_wa' => '6281234567890',
        'layanan' => 'Reset Passphrase',
        'status' => 'Done',
        'source' => 'direct',
        'ticket_number' => 'TTE-20261029-000001',
    ], $attrs));
}

function surveyTestUrl(Ticket $ticket): string {
    return URL::temporarySignedRoute('public.survey.show', now()->addDays(30), ['ticket' => $ticket->id]);
}

it('shows a responsive survey to the holder of a signed link and blocks unsigned requests', function () {
    $ticket = surveyTestTicket();
    $this->get('/survei/'.$ticket->id)->assertForbidden();
    $this->get(surveyTestUrl($ticket))->assertOk()->assertSee('Survei Kepuasan Layanan TTE');
    $this->get(URL::temporarySignedRoute('public.survey.show', now()->subMinute(), ['ticket'=>$ticket->id]))->assertForbidden();
});

it('accepts exactly one response and does not request sensitive identity information', function () {
    $ticket = surveyTestTicket();
    $url = surveyTestUrl($ticket);
    $this->get($url)->assertDontSee('Masukkan NIK');
    $payload = ['respondent_name'=>'Responden Survei Contoh', 'overall_rating'=>5, 'staff_rating'=>4, 'clarity_rating'=>4, 'speed_rating'=>5, 'feedback'=>'Sangat membantu'];
    $this->post($url, $payload)->assertRedirect(route('public.survey.thanks'));
    $this->post($url, $payload)->assertRedirect(route('public.survey.thanks'));
    expect(ServiceSurveyResponse::where('ticket_id', $ticket->id)->count())->toBe(1);
    expect(ServiceSurveyResponse::where('ticket_id', $ticket->id)->first()->respondent_name)->toBe('Responden Survei Contoh');
    $this->get($url)->assertSee('sudah diisi');
});

it('rejects invalid ratings and blocks surveys on unfinished tickets', function () {
    $done = surveyTestTicket();
    $url = surveyTestUrl($done);
    $this->post($url, ['overall_rating'=>6, 'staff_rating'=>5, 'clarity_rating'=>5, 'speed_rating'=>5])->assertSessionHasErrors('overall_rating');
    $unfinished = surveyTestTicket(['ticket_number'=>'TTE-20261029-000002', 'status'=>'In Progress']);
    $this->get(surveyTestUrl($unfinished))->assertNotFound();
    expect(ServiceSurveyResponse::count())->toBe(0);
});

it('includes a working signed survey link in the final WhatsApp message', function () {
    $ticket = surveyTestTicket();
    $message = app(WhatsAppMessage::class)->forCompletedTicket($ticket);
    expect($message)->toContain('/survei/'.$ticket->id)->toContain('signature=');
});

it('restricts survey statistics to admin users', function () {
    surveyTestTicket();
    $this->get(route('admin.surveys.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role'=>'verifier']))->get(route('admin.surveys.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.surveys.index'))->assertOk()->assertSee('Survei Kepuasan Layanan');
});

it('requires a respondent name and shows that name in the admin feedback table', function () {
    $ticket = surveyTestTicket(['nama_user' => 'Pemohon Pada Tiket']);
    $url = surveyTestUrl($ticket);
    $data = [
        'overall_rating' => 5,
        'staff_rating' => 4,
        'clarity_rating' => 5,
        'speed_rating' => 4,
        'feedback' => 'Masukan pengisi survei.',
    ];

    $this->post($url, $data)->assertSessionHasErrors('respondent_name');
    $this->assertDatabaseCount('service_survey_responses', 0);

    $this->post($url, $data + ['respondent_name' => 'Orang yang Mengisi Survei'])
        ->assertRedirect(route('public.survey.thanks'));

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.surveys.index'))
        ->assertOk()
        ->assertSee('Waktu')
        ->assertSee('Nomor Tiket')
        ->assertSee('Nama Pengisi Survei')
        ->assertSee('Nilai')
        ->assertSee('Saran / Masukan')
        ->assertSee('Orang yang Mengisi Survei')
        ->assertSee('Masukan pengisi survei.');
});
