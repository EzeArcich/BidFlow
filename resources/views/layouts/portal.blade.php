<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title','Portal de proveedores') · Bidflow</title>
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<header class="topbar gap-3">
<a class="brand brand-dark flex-shrink-0" href="{{ route('portal.home') }}">
<span class="brand-mark">b<span>↗</span>
</span>bidflow.</a>
<div class="d-flex align-items-center gap-3">
<span class="small text-secondary">Portal de proveedores · {{ auth('supplier')->user()->name }}</span>
<form method="post" action="{{ route('portal.logout') }}">
@csrf<button class="btn btn-outline-secondary btn-sm">Salir</button>
</form>
</div>
</header>
<main class="container py-4">
<x-alerts/>@yield('content')</main>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer>
</script>
<script src="{{ asset('js/admin.js') }}" defer>
</script>
</body>
</html>
