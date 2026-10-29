@extends('layouts.app')
@section('title', 'Survei Kepuasan Layanan TTE')
@section('content')
<link rel="stylesheet" href="{{ asset('css/survey.css') }}?v=1">
<div class="survey-wrap">
    <header class="survey-header">
        <span class="survey-mark" aria-hidden="true">T</span>
        <div>
            <span class="eyebrow">PEMERINTAH KABUPATEN SUBANG</span>
            <h1>Survei Kepuasan Layanan TTE</h1>
            <p class="muted">Masukan Anda membantu peningkatan mutu pelayanan.</p>
        </div>
    </header>
    <section class="card survey-card">
        <div class="survey-ticket"><span>Layanan: <strong>{{ $ticket->service_label }}</strong></span><span>No. tiket: <strong>{{ $ticket->ticket_number ?? '#' . $ticket->id }}</strong></span></div>
        @if ($alreadySubmitted)
            <div class="survey-success" role="status"><h2>Survei untuk tiket ini sudah diisi</h2><p>Terima kasih atas partisipasi Anda. Setiap tiket hanya dapat memberikan satu respons.</p></div>
        @else
            <h2>Bagaimana pengalaman layanan Anda?</h2>
            <p class="small muted">Pilih nilai 1 (sangat tidak puas) hingga 5 (sangat puas) untuk setiap pertanyaan. Form ini tidak meminta NIK maupun email.</p>
            <form method="post" action="{{ request()->fullUrl() }}" class="survey-form">
                @csrf
                <div class="honeypot" aria-hidden="true"><label>Biarkan kosong <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                @foreach ([
                    'overall_rating' => 'Kepuasan keseluruhan terhadap layanan',
                    'staff_rating' => 'Keramahan dan bantuan petugas',
                    'clarity_rating' => 'Kejelasan informasi yang diberikan',
                    'speed_rating' => 'Kecepatan penanganan permohonan'
                ] as $field => $label)
                    <fieldset class="survey-question">
                        <legend>{{ $loop->iteration }}. {{ $label }} <span aria-label="wajib">*</span></legend>
                        <div class="survey-scale" aria-label="Nilai {{ $label }}">
                            @for ($n = 1; $n <= 5; $n++)
                                <label class="survey-score"><input type="radio" name="{{ $field }}" value="{{ $n }}" required @checked((string) old($field) === (string) $n)><span>{{ $n }}</span></label>
                            @endfor
                        </div>
                        <div class="survey-scale-caption"><span>Sangat tidak puas</span><span>Sangat puas</span></div>
                        @error($field)<p class="survey-error">{{ $message }}</p>@enderror
                    </fieldset>
                @endforeach
                <label class="survey-feedback" for="survey-feedback">Saran atau masukan (opsional)
                    <textarea id="survey-feedback" name="feedback" rows="4" maxlength="1000" placeholder="Tuliskan saran untuk meningkatkan layanan kami...">{{ old('feedback') }}</textarea>
                </label>
                @error('feedback')<p class="survey-error">{{ $message }}</p>@enderror
                <p class="small muted">Tanggapan digunakan untuk evaluasi layanan. Hindari mencantumkan NIK, password, OTP, atau data pribadi sensitif pada kolom masukan.</p>
                <button type="submit" class="btn btn-primary full survey-submit">Kirim Survei Kepuasan</button>
            </form>
        @endif
    </section>
</div>
@endsection
