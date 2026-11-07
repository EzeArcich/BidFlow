
@extends('layouts.guest')
@section('title','Portal de proveedores')
@section('content')
<main class="selection-shell">
<a class="brand brand-dark" href="{{ route('portal.login') }}">
<span class="brand-mark">b<span>↗</span>
</span>bidflow.</a>
<section class="card form-card mx-auto mt-5" style="max-width:460px">
<div class="eyebrow">PORTAL DE PROVEEDORES</div>
<h1 class="h3 my-3">Tu próxima oportunidad.</h1>
<p class="text-secondary">Consultá tus invitaciones, presentá ofertas y seguí tus adjudicaciones.</p>
<x-alerts/>
<form method="post" action="{{ route('portal.login.store') }}">
@csrf<label class="form-label" for="email">Correo del proveedor</label>
<input class="form-control mb-3" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
<label for="password" class="form-label">Contraseña</label>
<input class="form-control mb-4" id="password" name="password" type="password" autocomplete="current-password" required>
<button class="btn btn-primary w-100">Ingresar al portal</button>
</form>
<p class="small text-secondary mt-4 mb-0">Tu acceso debe ser habilitado por un administrador. <a href="{{ route('login') }}">Acceso para organizaciones</a>
</p>
</section>
</main>
@endsection
