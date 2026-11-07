
@extends('layouts.admin')
@section('title', 'Resumen')
@section('content')
<div class="page-heading">
<div>
<div class="eyebrow">VISTA GENERAL</div>
<h1>Tu proceso, en perspectiva.</h1>
<p>El estado actual de las licitaciones de tu organización.</p>
</div>
@can('create', \App\Domain\Tenders\Models\Tender::class)<a class="btn btn-primary" href="{{ route('tenders.create') }}">
<x-icon name="plus"/>Nueva licitación</a>
@endcan</div>
<div class="row g-3 mb-4">
@foreach([['Total de licitaciones', $counts->sum(), 'file', null], ['En preparación', $counts['draft'] ?? 0, 'edit', 'draft'], ['Publicadas', $counts['published'] ?? 0, 'arrow', 'published'], ['En evaluación', $counts['closed'] ?? 0, 'clock', 'closed']] as [$label, $value, $icon, $filter])<div class="col-6 col-xl-3">
<a class="metric-card" href="{{ route('tenders.index', array_filter(['status' => $filter])) }}">
<span class="metric-head">{{ $label }}<span class="metric-icon">
<x-icon :name="$icon"/>
</span>
</span>
<strong data-metric="{{ $filter ?? 'total' }}">{{ $value }}</strong>
<span class="metric-link">Ver licitaciones <span>↗</span>
</span>
</a>
</div>
@endforeach</div>
<section class="overview-banner mb-4">
<div>
<span class="eyebrow light">DALE FORMA A TU PRÓXIMA COMPRA</span>
<h2>Una solicitud clara.<br>Un proceso que avanza.</h2>
<p>Definí qué necesitás, sumá a tus proveedores y publicá tu licitación.</p>
</div>
<div class="banner-steps">
<span>
<b>01</b>Definí el alcance</span>
<span>
<b>02</b>Elegí proveedores</span>
<span>
<b>03</b>Publicá tu solicitud</span>
</div>
</section>
<section class="card">
<div class="card-header d-flex justify-content-between align-items-center">
<div>
<h2 class="section-title">Licitaciones recientes</h2>
<p class="section-subtitle">Las últimas solicitudes creadas en tu organización.</p>
</div>
<a href="{{ route('tenders.index') }}" class="text-link">Ver todas <x-icon name="arrow"/>
</a>
</div>@include('tenders.table', ['tenders' => $recent])</section>
@endsection
