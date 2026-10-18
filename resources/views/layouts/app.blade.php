<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · Certrxy</title>
<link rel="stylesheet" href="{{ asset('css/certify.css') }}?v=2.0.34">
<link rel="stylesheet" href="{{ asset('css/certify-notices.css') }}?v=2.0.73">
<link rel="stylesheet" href="{{ asset('css/certify-about.css') }}?v=20261017-1">
@stack('styles')
<link rel="stylesheet" href="{{ asset('css/certrxy-svg-lockup-20261018.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/certrxy-sidebar-contrast-v4-20261018.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/certrxy-hero-svg-v3-20261018.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('css/certify-brand-refresh.css') }}?v=20261017">
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/certrxy-logo.svg') }}?v=certrxy-1">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=certrxy-1">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=certrxy-1">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=certrxy-1">
</head>
<body class="{{ request()->routeIs('templates.edit') ? 'certify-editor-route' : '' }}">
<a class="certify-skip-link" href="#main-content">Skip to main content</a>
<div class="shell">
    <aside class="side" id="sidebar">
        <a href="{{ route('dashboard') }}" class="brand" aria-label="Certrxy dashboard home">
            <img src="{{ asset('assets/certrxy-lockup-wide-20261018.svg') }}" alt="Certrxy logo" class="brand-logo">
        </a>
        <div class="nav-heading">WORKSPACE</div>
        <nav class="nav-menu" aria-label="Primary navigation">
            <a @if(request()->routeIs('dashboard')) aria-current="page" @endif class="navlink {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon">⌂</span><span>Dashboard</span></a>
            <a @if(request()->routeIs('templates.*')) aria-current="page" @endif class="navlink {{ request()->routeIs('templates.*') ? 'active' : '' }}" href="{{ route('templates.index') }}"><span class="nav-icon">▤</span><span>Templates</span></a>
            <a @if(request()->routeIs('fonts.*')) aria-current="page" @endif class="navlink {{ request()->routeIs('fonts.*') ? 'active' : '' }}" href="{{ route('fonts.index') }}"><span class="nav-icon">Aa</span><span>Fonts</span></a>
            <a @if(request()->routeIs('batches.create')) aria-current="page" @endif class="navlink {{ request()->routeIs('batches.create') ? 'active' : '' }}" href="{{ route('batches.create') }}"><span class="nav-icon">＋</span><span>Generate</span></a>
            <a @if(request()->routeIs('batches.index','batches.show')) aria-current="page" @endif class="navlink {{ request()->routeIs('batches.index','batches.show') ? 'active' : '' }}" href="{{ route('batches.index') }}"><span class="nav-icon">◷</span><span>History</span></a>
        </nav>
        <div class="certify-legal-bottom">
            <button type="button" class="navlink certify-legal-single certify-about-open" data-certify-about-open aria-haspopup="dialog" aria-controls="certifyAboutDialog">
                <span class="nav-icon" aria-hidden="true">&#9432;</span><span>About Certrxy</span>
            </button>
            <a class="navlink certify-legal-single {{ request()->routeIs('guide.index') ? 'active' : '' }}" href="{{ route('guide.index') }}" @if(request()->routeIs('guide.index')) aria-current="page" @endif>
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M3 18.5V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13.5a1 1 0 0 1-1 1h-4a4 4 0 0 0-4 2 4 4 0 0 0-4-2H4a1 1 0 0 1-1-1Z"/></svg>
                </span>
                <span>User Guide</span>
            </a>
            <a class="navlink certify-legal-single {{ request()->routeIs('legal.index','legal.document') ? 'active' : '' }}" href="{{ route('legal.index') }}" @if(request()->routeIs('legal.index','legal.document')) aria-current="page" @endif>
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                </span>
                <span>Legal &amp; Privacy</span>
            </a>
        </div>
    </aside>
    <div class="page-wrap">
        <div class="mobile-bar">
            <button type="button" class="mobile-menu" id="menuToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="sidebar">☰</button>
            <a class="mobile-brand" href="{{ route('dashboard') }}">
                <img src="{{ asset('assets/certrxy-lockup-wide-20261018.svg') }}" alt="Certrxy logo" class="mobile-brand-logo">
            </a>
        </div>
        <main id="main-content" tabindex="-1">
            @unless(trim($__env->yieldContent('hide_top')) === '1')
            <header class="top">
                <div class="heading"><div class="eyebrow">CERTIFICATE WORKSPACE</div><h1>@yield('title', 'Dashboard')</h1><p class="subtitle">@yield('subtitle', 'Create and export certificates simply.')</p></div>
                <div class="top-actions">@yield('topaction')</div>
            </header>
            @endunless
            {{-- Temporary action feedback disappears after five seconds; validation errors remain visible. --}}
            @foreach(['success' => 'success', 'warning' => 'warning', 'info' => 'info'] as $flashKey => $flashStyle)
                @if(session($flashKey))
                    <div class="notice notice-{{ $flashStyle }} certify-flash" data-certify-flash data-dismiss-after="5000" role="status">
                        <span class="certify-flash-message">{{ session($flashKey) }}</span>
                        <button type="button" class="certify-flash-close" aria-label="Dismiss notification" title="Dismiss notification">&times;</button>
                    </div>
                @endif
            @endforeach
            @if($errors->any())<div class="notice notice-error" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
            
        </main>
    </div>
</div>
<script>
(function(){const t=document.getElementById('menuToggle'),s=document.getElementById('sidebar');if(t&&s)t.addEventListener('click',()=>{const isOpen=s.classList.toggle('mobile-open');t.setAttribute('aria-expanded',String(isOpen));});})();
</script>
<script src="{{ asset('js/certify-notices.js') }}?v=2.0.73" defer></script>
<div class="certify-about-overlay" id="certifyAboutDialog" hidden>
    <section class="certify-about-dialog" role="dialog" aria-modal="true" aria-labelledby="certifyAboutTitle" aria-describedby="certifyAboutDescription">
        <header class="certify-about-header">
            <div class="certify-about-brand">
                <img src="/assets/certrxylogo.svg?v=about-20261018-1" alt="Certrxy logo">
                <div><h2 id="certifyAboutTitle">Certrxy</h2><p>Certificate Studio - Version {{ config('nativephp.version', '1.0.0') }}</p></div>
            </div>
            <button type="button" class="certify-about-close" data-certify-about-close aria-label="Close About Certrxy">&times;</button>
        </header>
        <p class="certify-about-description" id="certifyAboutDescription">Create, customize and export certificates, including offline PDF processing.</p>
        <dl class="certify-about-fields">
            <div><dt>Developer credit</dt><dd>George Rexy Vincent Z. Bacani (grvzb)</dd></div>
            <div><dt>Support</dt><dd><a href="mailto:grvzb11@gmail.com">grvzb11@gmail.com</a></dd></div>
            <div><dt>License</dt><dd><a href="{{ route('legal.index') }}#licenses">License information and third-party notices</a></dd></div>
            <div><dt>Updates</dt><dd>Manual application updates. Automatic update checking is not enabled.</dd></div>
        </dl>
        <p class="certify-about-update">Your locally saved certificates and templates should be preserved across updates. Keep your own backups before installing a new version.</p>
    </section>
</div>
<script src="{{ asset('js/certify-about.js') }}?v=20261017-1" defer></script>
@stack('scripts')
</body>
</html>
