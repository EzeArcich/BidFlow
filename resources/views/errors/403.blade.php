
@extends('layouts.guest')
@section('title', 'No tenés acceso a esta página')
@section('content')<main class="error-shell">
<a class="brand brand-dark" href="{{ route('home') }}">
<span class="brand-mark">b<span>↗</span>
</span>bidflow.</a>
<div class="error-number">403</div>
<h1>No tenés acceso a esta página</h1>
<p>Tu rol o membresía no permite realizar esta operación. Podés volver al resumen o elegir otra organización.</p>
<a class="btn btn-primary" href="{{ route('home') }}">Volver al inicio</a>
</main>
@endsection
