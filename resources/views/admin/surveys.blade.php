@extends('layouts.app')
@section('title', 'Hasil Survei Kepuasan')
@section('content')
<link rel="stylesheet" href="{{ asset('css/survey.css') }}?v=1">
<div class="page-heading"><div><div class="eyebrow">ADMIN / EVALUASI LAYANAN</div><h1>Survei Kepuasan Layanan TTE</h1><p class="muted">Ringkasan masukan pemohon yang mengisi survei setelah penanganan tiket.</p></div><a href="{{ route('admin.templates.index') }}" class="btn btn-outline">Preset WhatsApp</a></div>
<div class="stat-grid survey-stat-grid">
    @foreach (['total' => 'Jumlah Respons', 'overall' => 'Kepuasan Umum', 'staff' => 'Pelayanan Petugas', 'clarity' => 'Kejelasan Informasi', 'speed' => 'Kecepatan Penanganan'] as $key => $label)
        <div class="stat-card"><div class="stat-name">{{ $label }}</div><div class="stat-number">{{ $key === 'total' ? (int) $summary->total : ($summary->$key === null ? '-' : number_format((float) $summary->$key, 2, ',', '.')) }}</div><div class="stat-foot">{{ $key === 'total' ? 'Survei terkumpul' : 'Skala 1–5' }}</div></div>
    @endforeach
</div>
<section class="card mt"><h2>Rekap per Jenis Layanan</h2><div class="table-scroll"><table><thead><tr><th>Jenis Layanan</th><th>Respons</th><th>Rata-rata Kepuasan</th></tr></thead><tbody>
@forelse ($perService as $service)<tr><td>{{ $service->layanan }}</td><td>{{ $service->total }}</td><td>{{ number_format((float) $service->average, 2, ',', '.') }} / 5</td></tr>
@empty<tr><td colspan="3">Belum ada tanggapan.</td></tr>@endforelse
</tbody></table></div></section>
<section class="card mt"><h2>Masukan Pemohon</h2><p class="small muted">Masukan hanya diperlihatkan kepada administrator. Hindari membagikan identitas pemohon dari tiket.</p><div class="table-scroll"><table><thead><tr><th>Waktu</th><th>Tiket</th><th>Nilai</th><th>Saran / Masukan</th></tr></thead><tbody>
@forelse($responses as $response)<tr><td>{{ $response->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td><td>{{ $response->ticket?->ticket_number ?? '-' }}</td><td>{{ $response->overall_rating }}/5</td><td class="survey-comment">{{ $response->feedback ?: '-' }}</td></tr>
@empty<tr><td colspan="4">Belum ada survei yang masuk.</td></tr>@endforelse
</tbody></table></div>{{ $responses->links() }}</section>
@endsection
