<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#14243b">
    <title>@yield('title', 'Layanan TTE') · TTE Ticketing</title>
    <link rel="stylesheet" href="{{ asset('css/tte.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/tte-ui-fixes.css') }}?v=1">
    <script src="{{ asset('js/tte.js') }}?v=3" defer></script>
</head>

<body>
    {{-- Ikon SVG lokal. Tidak memerlukan CDN / webfont. --}}
    <svg class="tte-icon-sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
        <symbol id="tte-ico-stats" viewBox="0 0 24 24">
            <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 17v-5M12 17V7M17 17v-7"/>
        </symbol>
        <symbol id="tte-ico-users" viewBox="0 0 24 24">
            <path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
        </symbol>
        <symbol id="tte-ico-report" viewBox="0 0 24 24">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
            <path d="M14 2v6h6M8 13h8M8 17h8"/>
        </symbol>
        <symbol id="tte-ico-survey" viewBox="0 0 24 24">
            <rect x="4" y="3" width="16" height="18" rx="2"/>
            <path d="m8 9 1.5 1.5L12 8M14 9h3M8 16h9"/>
        </symbol>
        <symbol id="tte-ico-message" viewBox="0 0 24 24">
            <path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.95L3 21l1.95-5.5A9 9 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3a8.5 8.5 0 0 1 8.5 8.5Z"/>
            <path d="M8 11.5h9M8 15h6"/>
        </symbol>
        <symbol id="tte-ico-ticket" viewBox="0 0 24 24">
            <path d="M4 5h16v5a2 2 0 0 0 0 4v5H4v-5a2 2 0 0 0 0-4V5Z"/>
            <path d="M12 5v2M12 10v4M12 17v2"/>
        </symbol>
        <symbol id="tte-ico-external" viewBox="0 0 24 24">
            <path d="M14 4h6v6M20 4l-9 9"/>
            <path d="M20 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5"/>
        </symbol>
        <symbol id="tte-ico-profile" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
        </symbol>
    </svg>
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
                            href="{{ route('admin.dashboard') }}"><svg class="nav-symbol" aria-hidden="true" focusable="false"><use href="#tte-ico-stats" /></svg> Statistik Layanan</a>
                        <a class="nav-item {{ request()->routeIs('admin.verifiers.*') ? 'active' : '' }}"
                            href="{{ route('admin.verifiers.index') }}"><svg class="nav-symbol" aria-hidden="true" focusable="false"><use href="#tte-ico-users" /></svg> Manajemen Verifikator</a>
                        <a class="nav-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
                            href="{{ route('admin.reports.tickets.index') }}"><svg class="nav-symbol" aria-hidden="true" focusable="false"><use href="#tte-ico-report" /></svg> Laporan Tiket</a>
                        <a class="nav-item {{ request()->routeIs('admin.surveys.*') ? 'active' : '' }}"
                            href="{{ route('admin.surveys.index') }}"><svg class="nav-symbol" aria-hidden="true" focusable="false"><use href="#tte-ico-survey" /></svg> Hasil Survei</a>
                        <a class="nav-item {{ request()->routeIs('admin.templates.*') ? 'active' : '' }}"
                            href="{{ route('admin.templates.index') }}"><svg class="nav-symbol" aria-hidden="true" focusable="false"><use href="#tte-ico-message" /></svg> Preset
                            WhatsApp</a>
                    @else
                        <a class="nav-item {{ request()->routeIs('tickets.*') ? 'active' : '' }}"
                            href="{{ route('tickets.index') }}"><svg class="nav-symbol" aria-hidden="true" focusable="false"><use href="#tte-ico-ticket" /></svg> Antrean Tiket</a>
                    @endif
                    <a class="nav-item" href="{{ route('public.form') }}" target="_blank" rel="noopener"><svg class="nav-symbol" aria-hidden="true" focusable="false"><use href="#tte-ico-external" /></svg> Formulir Publik</a>
                </div>
                <div class="sidebar-help"><span class="mini-dot"></span><strong>Self-Claim Ticketing</strong>
                    <p>Permohonan dicatat secara transparan. Setiap tiket memiliki penanggung jawab.</p>
                </div>
                <div class="sidebar-bottom">
                    <div class="profile-chip"><span
                            class="avatar">{{ strtoupper(substr(auth()->user()->initials ?: auth()->user()->name, 0, 2)) }}</span><span><strong
                                class="user-name">{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrator' : 'Verifikator' }}</small></span>
                    </div>
                    <a href="{{ route('profile') }}" class="nav-item sidebar-secondary"><svg class="nav-symbol" aria-hidden="true" focusable="false"><use href="#tte-ico-profile" /></svg> Pengaturan Profil</a>
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
