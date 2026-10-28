<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Layanan TTE') — Manajemen TTE</title>
    <link rel="stylesheet" href="{{ asset('css/tte.css') }}">
</head>
<body>
@auth
<div class="shell">
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand"><span class="brand-icon">T</span><span>Manajemen TTE<small>Layanan Sertifikat Elektronik</small></span></a>
        <div class="nav-caption">MENU UTAMA</div>
        @if(auth()->user()->isAdmin())
            <a class="nav-item {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">▦ &nbsp; Statistik &amp; Akun</a>
        @else
            <a class="nav-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}" href="{{ route('tickets.index') }}">▤ &nbsp; Antrean Tiket</a>
        @endif
        <div class="sidebar-bottom">
            <div class="small muted">MASUK SEBAGAI</div>
            <div class="user-name">{{ auth()->user()->name }}</div>
            <div class="small muted">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Verifikator' }}</div>
            <a href="{{ route('profile') }}" class="nav-item mt">⚙ &nbsp; Profil &amp; Password</a>
            <form method="post" action="{{ route('logout') }}" class="mt">@csrf<button class="btn btn-ghost full" type="submit">Keluar</button></form>
        </div>
    </aside>
    <main class="main">
        <div class="topbar"><span class="muted">Sistem Informasi Manajemen Layanan TTE</span><span class="pill pill-neutral">{{ now('Asia/Jakarta')->translatedFormat('d M Y') }}</span></div>
        <div class="content">
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert error"><strong>Periksa kembali:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </main>
</div>
@else
<main class="auth-page">
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@endauth
</body>
</html>
