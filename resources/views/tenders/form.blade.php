
@extends('layouts.admin')
@section('title', $tender ? 'Editar licitación' : 'Nueva licitación')
@section('content')
@php($localDeadline = $tender?->deadline_at?->setTimezone(config('bidding.display_timezone'))->format('Y-m-d\TH:i'))
<div class="page-heading">
<div>
<a class="back-link" href="{{ $tender ? route('tenders.show', $tender) : route('tenders.index') }}">← Volver a {{ $tender ? 'la licitación' : 'licitaciones' }}</a>
<h1>{{ $tender ? 'Editar licitación' : 'Empecemos por lo esencial.' }}</h1>
<p>Describí tu necesidad. Los ítems y proveedores se agregan en el siguiente paso.</p>
</div>
<span class="step-label">01 / DEFINIR SOLICITUD</span>
</div>
<div class="row g-4">
<div class="col-xl-8">
<form method="post" action="{{ $tender ? route('tenders.update', $tender) : route('tenders.store') }}" class="card form-card">
@csrf @if($tender) @method('PATCH') @endif<h2 class="section-title mb-4">Información general</h2>
<div class="mb-4">
<label for="title" class="form-label">Título de la licitación <span class="required">*</span>
</label>
<input id="title" class="form-control @error('title') is-invalid @enderror" name="title" value="{{ old('title', $tender?->title) }}" maxlength="255" required placeholder="Ej.: Servicio de mantenimiento de oficinas" autofocus>
</div>
<div class="row g-3 mb-4">
<div class="col-md-6">
<label for="type" class="form-label">Categoría <span class="required">*</span>
</label>
@if($tender && !in_array($tender->type->value, ['products','services','works']))<input class="form-control" value="{{ config('ui.types.'.$tender->type->value) }}" id="type" disabled>
<div class="form-text">Se conserva la categoría de esta solicitud existente.</div>
@else<select id="type" name="type" class="form-select" required>
<option value="">Elegí una categoría</option>
@foreach(['products','services','works'] as $type)<option value="{{ $type }}" @selected(old('type', $tender?->type->value) === $type)>{{ config('ui.types.'.$type) }}</option>
@endforeach</select>
@endif</div>
<div class="col-md-6">
<label for="deadline_local" class="form-label">Fecha y hora límite <span class="required">*</span>
</label>
<input id="deadline_local" name="deadline_local" type="datetime-local" class="form-control @error('deadline_at') is-invalid @enderror" value="{{ old('deadline_local', $localDeadline) }}" required>
<div class="form-text">Horario de Buenos Aires (UTC−3).</div>
</div>
</div>
<div class="mb-4">
<label class="form-label" for="description">Descripción</label>
<textarea id="description" name="description" rows="4" class="form-control" maxlength="10000" placeholder="Contexto, alcance y objetivos de la solicitud.">{{ old('description', $tender?->description) }}</textarea>
</div>
<div class="mb-4">
<label class="form-label" for="conditions_text">Condiciones de contratación</label>
<textarea id="conditions_text" name="conditions_text" rows="3" class="form-control" maxlength="10000" placeholder="Lugar de entrega, plazos, requisitos o condiciones generales.">{{ old('conditions_text', is_string(data_get($tender?->conditions, 'notes')) ? data_get($tender?->conditions, 'notes') : '') }}</textarea>
</div>
<div class="row g-3 mb-4">
<div class="col-md-8">
<label class="form-label" for="external_reference">Referencia interna <span class="optional">opcional</span>
</label>
<input id="external_reference" name="external_reference" class="form-control" value="{{ old('external_reference', $tender?->external_reference) }}" maxlength="255" placeholder="Ej.: SOL-2026-001">
</div>
<div class="col-md-4">
<label for="currency" class="form-label">Moneda</label>
<select id="currency" class="form-select" name="currency">
<option value="ARS">ARS · Peso argentino</option>
</select>
</div>
</div>
<div class="form-actions">
<a class="btn btn-outline-secondary" href="{{ $tender ? route('tenders.show', $tender) : route('tenders.index') }}">Cancelar</a>
<button type="submit" class="btn btn-primary">{{ $tender ? 'Guardar cambios' : 'Crear licitación' }}<x-icon name="arrow"/>
</button>
</div>
</form>
</div>
<div class="col-xl-4">
<aside class="help-card">
<span class="help-kicker">UN BUEN COMIENZO</span>
<h3>La claridad hace<br>la diferencia.</h3>
<p>Un título preciso y un alcance bien definido ayudan a los proveedores a entender qué necesitás.</p>
<hr>
<div class="help-step">
<b>1</b>
<div>
<strong>Creá tu solicitud</strong>
<small>Completá la información general.</small>
</div>
</div>
<div class="help-step">
<b>2</b>
<div>
<strong>Sumá ítems y proveedores</strong>
<small>Definí cantidades y elegí a quién invitar.</small>
</div>
</div>
<div class="help-step">
<b>3</b>
<div>
<strong>Revisá y publicá</strong>
<small>Confirmá los datos antes de avanzar.</small>
</div>
</div>
</aside>
</div>
</div>
@endsection
