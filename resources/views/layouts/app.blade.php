<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#14243b">
    <title>@yield('title', 'Layanan TTE') · TTE Ticketing</title>
    <link rel="stylesheet" href="{{ asset('css/tte.css') }}?v=3">
    <script src="{{ asset('js/tte.js') }}?v=3" defer></script>
</head>

<body>
    @auth
        <div class="shell">
            <aside class="sidebar">
                <a href="{{ route('dashboard') }}" class="brand" aria-label="Dashboard TTE Ticketing">
                    <span class="brand-icon">T</span>
                    <span>TTE Ticketing<small>Layanan Sertifikat Elektronik</small></span>
                </a>
                <div class="sidebar-group">
                    <div class="nav-caption">MENU UTAMA</div>
                    @if (auth()->user()->isAdmin())
                        <a class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                            href="{{ route('admin.dashboard') }}"><span class="nav-symbol">▦</span> Statistik Layanan</a>
                        <a class="nav-item {{ request()->routeIs('admin.surveys.*') ? 'active' : '' }}"
                            href="{{ route('admin.surveys.index') }}"><span class="nav-symbol">▤</span> Hasil Survei</a>
                        <a class="nav-item {{ request()->routeIs('admin.templates.*') ? 'active' : '' }}"
                            href="{{ route('admin.templates.index') }}"><span class="nav-symbol">✉</span> Preset
                            WhatsApp</a>
                    @else
                        <a class="nav-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}"
                            href="{{ route('tickets.index') }}"><span class="nav-symbol">▤</span> Antrean Tiket</a>
                    @endif
                    <a class="nav-item" href="{{ route('public.form') }}" target="_blank" rel="noopener"><span
                            class="nav-symbol">↗</span> Formulir Publik</a>
                </div>
                <div class="sidebar-help"><span class="mini-dot"></span><strong>Self-Claim Ticketing</strong>
                    <p>Permohonan dicatat secara transparan. Setiap tiket memiliki penanggung jawab.</p>
                </div>
                <div class="sidebar-bottom">
                    <div class="profile-chip"><span
                            class="avatar">{{ strtoupper(substr(auth()->user()->initials ?: auth()->user()->name, 0, 2)) }}</span><span><strong
                                class="user-name">{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrator' : 'Verifikator' }}</small></span>
                    </div>
                    <a href="{{ route('profile') }}" class="nav-item sidebar-secondary">Pengaturan Profil</a>
                    <form method="post" action="{{ route('logout') }}">@csrf<button
                            class="btn btn-ghost full sidebar-logout" type="submit">Keluar Sistem</button></form>
                </div>
            </aside>
            <main class="main">
                <header class="topbar"><span><span class="topbar-circle"></span> Sistem Manajemen Layanan TTE</span><span
                        class="topbar-date">{{ now('Asia/Jakarta')->translatedFormat('l, d F Y') }}</span></header>
                <div class="content">
                    @if (session('success'))
                        <div class="alert success" role="status">{{ session('success') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert error" role="alert"><strong>Mohon periksa kembali:</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @yield('content')
                </div>
            </main>
        </div>
    @else
        <main class="auth-page {{ request()->routeIs('public.*') ? 'public-auth-page' : '' }}">
            @if (session('success'))
                <div class="alert success" role="status">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    @endauth
    <div id="clipboard-status" class="clipboard-status" role="status" aria-live="polite"></div>
</body>

</html>
