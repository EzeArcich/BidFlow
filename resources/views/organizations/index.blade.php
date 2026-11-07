
@extends('layouts.guest')
@section('title', 'Elegir organización')
@section('content')
<div class="selection-shell">
<div class="selection-top">
<a class="brand brand-dark" href="{{ route('home') }}">
<span class="brand-mark">b<span>↗</span>
</span>bidflow.</a>
<form method="post" action="{{ route('logout') }}">
@csrf<button class="btn btn-outline-secondary btn-sm">Cerrar sesión</button>
</form>
</div>
<div class="selection-content">
<div class="eyebrow">HOLA, {{ auth('web')->user()->name }}</div>
<h1>Elegí tu espacio de trabajo</h1>
<p class="text-secondary mb-4">Cada organización tiene sus propias licitaciones y proveedores.</p>
<x-alerts/>
<div class="row g-3">
@forelse($organizations as $organization)<div class="col-md-6">
<form method="post" action="{{ route('organizations.store') }}">
@csrf<input type="hidden" name="organization_id" value="{{ $organization->id }}">
<button class="organization-card w-100 text-start" type="submit">
<span class="organization-symbol">
<x-icon name="building"/>
</span>
<strong>{{ $organization->name }}</strong>
<span class="d-flex justify-content-between align-items-center mt-3">
<span class="text-secondary small">{{ config('ui.roles.'.$organization->pivot->role) }}</span>
<x-icon name="arrow"/>
</span>
</button>
</form>
</div>
@empty<div class="col">
<div class="empty-state card">
<x-icon name="building"/>
<h2>No tenés una organización activa</h2>
<p>Pedile a tu administrador que te asigne una membresía para empezar a trabajar.</p>
</div>
</div>
@endforelse</div>
</div>
</div>
@endsection
