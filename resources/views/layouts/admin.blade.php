
@php($activeOrganization = request()->attributes->get('activeOrganization'))
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Panel') · Bidflow</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<a class="visually-hidden-focusable skip-link" href="#main">Ir al contenido</a>
<aside class="sidebar offcanvas-lg offcanvas-start" id="sidebar" tabindex="-1" aria-label="Navegación principal">
<div class="brand-row">
<a class="brand" href="{{ route('dashboard') }}">
<span class="brand-mark">b<span>↗</span>
</span>bidflow<span class="brand-dot">.</span>
</a>
<button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar menú">
</button>
</div>
<div class="nav-caption">ESPACIO DE TRABAJO</div>
<nav class="side-nav">
<a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')]) @if(request()->routeIs('dashboard')) aria-current="page" @endif>
<x-icon name="dashboard"/>Resumen</a>
<a href="{{ route('tenders.index') }}" @class(['active' => request()->routeIs('tenders.*', 'items.*')]) @if(request()->routeIs('tenders.*', 'items.*')) aria-current="page" @endif>
<x-icon name="file"/>Licitaciones</a>
<a href="{{ route('suppliers.index') }}" @class(['active' => request()->routeIs('suppliers.*')]) @if(request()->routeIs('suppliers.*')) aria-current="page" @endif>
<x-icon name="users"/>Proveedores</a>
</nav>
<div class="sidebar-bottom">
<div class="workspace-label">
<span class="workspace-icon">
<x-icon name="building"/>
</span>
<div>
<small>ORGANIZACIÓN ACTIVA</small>
<strong>{{ $activeOrganization->name }}</strong>
</div>
</div>
<a class="switch-org" href="{{ route('organizations.index') }}">Cambiar organización <span>↗</span>
</a>
<div class="sidebar-note">Un mejor proceso.<br>Mejores decisiones.</div>
</div>
</aside>
<div class="admin-main">
<header class="topbar">
<div class="d-flex align-items-center gap-3">
<button class="btn btn-light d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menú">
<x-icon name="menu"/>
</button>
<span class="topbar-label">Gestión de compras <span class="mx-2 text-muted">/</span> <strong>@yield('title', 'Panel')</strong>
</span>
</div>
<div class="d-flex align-items-center gap-3">
<span class="role-label d-none d-md-block">{{ config('ui.roles.'.$activeOrganization->pivot->role) }}</span>
<div class="user-avatar" title="{{ auth('web')->user()->name }}">{{ mb_strtoupper(mb_substr(auth('web')->user()->name, 0, 1)) }}</div>
<form method="post" action="{{ route('logout') }}">
@csrf<button class="btn btn-logout" title="Cerrar sesión" aria-label="Cerrar sesión">
<x-icon name="logout"/>
</button>
</form>
</div>
</header>
<main id="main" class="content-wrap">
<x-alerts/>@yield('content')<footer class="app-footer">
<span>Bidflow · Compras con perspectiva.</span>
<span>{{ $activeOrganization->name }}</span>
</footer>
</main>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer>
</script>
<script src="{{ asset('js/admin.js') }}" defer>
</script>
</body>
</html>
