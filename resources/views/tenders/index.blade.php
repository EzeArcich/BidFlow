
@extends('layouts.admin')
@section('title', 'Licitaciones')
@section('content')
<div class="page-heading">
<div>
<div class="eyebrow">SOLICITUDES DE COTIZACIÓN</div>
<h1>Licitaciones</h1>
<p>Todo lo que tu organización necesita, en un solo lugar.</p>
</div>
@can('create', \App\Domain\Tenders\Models\Tender::class)<a href="{{ route('tenders.create') }}" class="btn btn-primary">
<x-icon name="plus"/>Nueva licitación</a>
@endcan</div>
<section class="card">
<form method="get" action="{{ route('tenders.index') }}" class="filter-bar">
<div class="search-field">
<label class="visually-hidden" for="q">Buscar licitación</label>
<x-icon name="search"/>
<input id="q" name="q" class="form-control" placeholder="Buscar por título..." value="{{ request('q') }}" maxlength="200">
</div>
<div>
<label class="visually-hidden" for="status">Estado</label>
<select id="status" name="status" class="form-select">
<option value="">Todos los estados</option>
@foreach(config('ui.statuses') as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
@endforeach</select>
</div>
<div>
<label class="visually-hidden" for="type">Categoría</label>
<select id="type" name="type" class="form-select">
<option value="">Todas las categorías</option>
@foreach(config('ui.types') as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
@endforeach</select>
</div>
<button class="btn btn-outline-secondary">Filtrar</button>
@if(request()->hasAny(['q','status','type']))<a href="{{ route('tenders.index') }}" class="text-link small">Limpiar</a>
@endif</form>@include('tenders.table')<div class="card-footer">{{ $tenders->links() }}</div>
</section>
@endsection
