
@extends('layouts.guest')
@section('title', 'Iniciar sesión')
@section('content')
<div class="login-shell">
<section class="login-story">
<a class="brand" href="{{ route('home') }}">
<span class="brand-mark">b<span>↗</span>
</span>bidflow<span class="brand-dot">.</span>
</a>
<div class="story-copy">
<div class="eyebrow light">CADA COMPRA, UN NUEVO PASO</div>
<h1>De una necesidad<br>a una buena<br>
<span>decisión.</span>
</h1>
<p>Organizá tus licitaciones, conectá con proveedores y llevá cada solicitud hacia adelante.</p>
<div class="story-tags">
<span>Productos</span>
<span>Servicios</span>
<span>Obras</span>
</div>
</div>
<div class="story-footer">
<span class="story-line">
</span>Un espacio para todo tu proceso de compras.</div>
</section>
<section class="login-form-panel">
<div class="login-form">
<div class="eyebrow">TU ESPACIO DE TRABAJO</div>
<h2>Bienvenido a Bidflow</h2>
<p class="text-secondary mb-4">Ingresá con tu cuenta para continuar.</p>
<x-alerts/>
<form method="post" action="{{ route('login.store') }}">
@csrf<div class="mb-3">
<label for="email" class="form-label">Correo electrónico</label>
<input id="email" name="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="nombre@empresa.com" autocomplete="username" required autofocus maxlength="255">
</div>
<div class="mb-4">
<label for="password" class="form-label">Contraseña</label>
<input id="password" name="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" autocomplete="current-password" required maxlength="1024">
</div>
<button class="btn btn-primary btn-lg w-100 d-flex justify-content-between align-items-center" type="submit">Iniciar sesión <x-icon name="arrow"/>
</button>
</form>
<p class="login-help">¿Necesitás acceso? Contactá al administrador de tu organización.</p>
</div>
<div class="login-footnote">Licitaciones claras. Relaciones que avanzan.</div>
</section>
</div>
@endsection
