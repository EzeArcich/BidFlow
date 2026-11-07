
@extends('layouts.guest')
@section('title', 'No encontramos esta página')
@section('content')<main class="error-shell">
<a class="brand brand-dark" href="{{ route('home') }}">
<span class="brand-mark">b<span>↗</span>
</span>bidflow.</a>
<div class="error-number">404</div>
<h1>No encontramos esta página</h1>
<p>La solicitud no existe o no está disponible en tu organización actual.</p>
<a class="btn btn-primary" href="{{ route('home') }}">Volver al inicio</a>
</main>
@endsection
