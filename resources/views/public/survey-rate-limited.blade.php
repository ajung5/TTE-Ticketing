@extends('layouts.app')
@section('title', 'Batas Akses Survei')
@section('content')
    <link rel="stylesheet" href="{{ asset('css/survey.css') }}?v=1">
    <div class="survey-wrap">
        <section class="card survey-card" role="alert">
            <div class="eyebrow">SURVEI KEPUASAN LAYANAN TTE</div>
            <h1>{{ $isSubmission ? 'Pengiriman Survei Dibatasi Sementara' : 'Akses Survei Dibatasi Sementara' }}</h1>
            @if ($isSubmission)
                <p>Pengiriman kali ini belum diproses. Isian Anda disimpan sementara pada sesi browser ini.</p>
            @else
                <p>Halaman survei terlalu sering dibuka dalam waktu singkat.</p>
            @endif
            <p>Silakan coba kembali setelah sekitar <strong>{{ $retryAfter }} detik</strong>.</p>
            <p class="small muted">Setiap tiket hanya dapat memiliki satu jawaban survei. Jika sudah tercatat,
                halaman formulir akan menampilkan pemberitahuan bahwa survei sudah diisi.</p>
            <a class="btn btn-primary" href="{{ $retryUrl }}">Kembali ke Formulir Survei</a>
        </section>
    </div>
@endsection
