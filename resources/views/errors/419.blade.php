
@extends('layouts.guest')
@section('title', 'Tu sesión necesita renovarse')
@section('content')<main class="error-shell">
<a class="brand brand-dark" href="{{ route('home') }}">
<span class="brand-mark">b<span>↗</span>
</span>bidflow.</a>
<div class="error-number">419</div>
<h1>Tu sesión necesita renovarse</h1>
<p>Volvé a ingresar y repetí la operación. Tus datos no fueron modificados.</p>
<a class="btn btn-primary" href="{{ route('home') }}">Volver al inicio</a>
</main>
@endsection
