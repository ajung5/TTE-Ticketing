@extends('layouts.app')
@section('title', 'Terima Kasih')
@section('content')
<link rel="stylesheet" href="{{ asset('css/survey.css') }}?v=1">
<div class="survey-wrap"><section class="card survey-card survey-thanks"><div class="survey-check" aria-hidden="true">✓</div><h1>Terima kasih atas penilaian Anda</h1><p class="muted">Survei kepuasan layanan TTE telah tercatat. Masukan Anda digunakan untuk meningkatkan pelayanan.</p><a href="{{ route('public.form') }}" class="btn btn-outline">Kembali ke Layanan TTE</a></section></div>
@endsection
