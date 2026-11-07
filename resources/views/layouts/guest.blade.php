<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Bienvenido') · Bidflow</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="guest-page">@yield('content')<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer>
</script>
<script src="{{ asset('js/admin.js') }}" defer>
</script>
</body>
</html>
