
@extends('layouts.admin')
@section('title', 'Detalle de licitación')
@section('content')
<div class="page-heading">
<div>
<a href="{{ route('tenders.index') }}" class="back-link">← Todas las licitaciones</a>
<div class="d-flex align-items-center gap-3 flex-wrap">
<h1 class="mb-0">{{ $tender->title }}</h1>
<x-status :status="$tender->status"/>
</div>
<p class="mt-2">{{ config('ui.types.'.$tender->type->value) }} @if($tender->external_reference) · {{ $tender->external_reference }} @endif @if($tender->is_demo)<span class="demo-label">DATOS DEMO</span>
@endif</p>
</div>
<a class="btn btn-outline-primary btn-sm" href="{{ route('tenders.evaluation',$tender) }}">Ofertas y adjudicaciones</a>
@can('update', $tender)<div class="d-flex gap-2 flex-wrap">
<a href="{{ route('tenders.edit', $tender) }}" class="btn btn-outline-secondary">
<x-icon name="edit"/>Editar</a>
<a href="{{ route('tenders.confirm', $tender) }}" class="btn btn-primary">Revisar y publicar<x-icon name="arrow"/>
</a>
</div>
@endcan</div>
@if($tender->status->value !== 'draft')<div class="notice">
<x-icon name="check"/>
<span>Esta licitación está en modo de consulta. Sus datos ya no pueden modificarse.</span>
</div>
@endif
<div class="row g-4">
<div class="col-xl-8">
<section class="card mb-4">
<div class="card-header">
<h2 class="section-title">Información de la solicitud</h2>
</div>
<div class="card-body">
<p class="preserve-lines">{{ $tender->description ?: 'No se agregó una descripción.' }}</p>
@if($tender->conditions)<h3 class="mini-heading">Condiciones de contratación</h3>
<dl class="spec-list">
@foreach($tender->conditions as $key => $value)<div>
<dt>{{ $key === 'notes' ? 'Condiciones' : $key }}</dt>
<dd class="preserve-lines">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? 'Sí' : 'No') : $value) }}</dd>
</div>
@endforeach</dl>
@endif</div>
</section>
<section class="card mb-4">
<div class="card-header d-flex justify-content-between align-items-center">
<div>
<h2 class="section-title">Ítems solicitados <span class="count-pill">{{ $tender->items->count() }}</span>
</h2>
<p class="section-subtitle">El alcance que recibirán tus proveedores.</p>
</div>
@can('update', $tender)<a href="{{ route('items.create', $tender) }}" class="btn btn-outline-primary btn-sm">
<x-icon name="plus"/>Agregar ítem</a>
@endcan</div>
@forelse($tender->items as $item)<article class="item-entry">
<div class="item-number">{{ $item->position }}</div>
<div class="flex-grow-1">
<div class="d-flex justify-content-between gap-3">
<h3>{{ $item->description }}</h3>
@can('update', $tender)<a class="text-link small text-nowrap" href="{{ route('items.edit', [$tender, $item->id]) }}">Editar</a>
@endcan</div>
<div class="item-quantity">{{ $item->quantity }} <span>{{ $item->unit }}</span>
</div>
@if($item->reference_code)<small class="text-secondary">Referencia: {{ $item->reference_code }}</small>
@endif
@if($item->attributes)<dl class="spec-list mt-3">
@foreach($item->attributes as $key => $value)<div>
<dt>{{ $key === 'specifications' ? 'Especificaciones' : $key }}</dt>
<dd class="preserve-lines">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? 'Sí' : 'No') : $value) }}</dd>
</div>
@endforeach</dl>
@endif @if($item->notes)<p class="small text-secondary mt-2 preserve-lines">{{ $item->notes }}</p>
@endif @can('update', $tender)<form method="post" action="{{ route('items.destroy', [$tender, $item->id]) }}" data-confirm="¿Eliminar este ítem de la licitación?">
@csrf @method('DELETE')<button class="btn btn-link text-danger p-0 small mt-2" type="submit">Eliminar ítem</button>
</form>
@endcan</div>
</article>
@empty<div class="empty-state compact">
<x-icon name="file"/>
<h3>Definí lo que necesitás</h3>
<p>Agregá al menos un ítem para poder publicar la licitación.</p>
@can('update', $tender)<a href="{{ route('items.create', $tender) }}" class="text-link">Agregar el primer ítem →</a>
@endcan</div>
@endforelse</section>
<section class="card">
<div class="card-header d-flex justify-content-between align-items-center">
<div>
<h2 class="section-title">Proveedores invitados <span class="count-pill">{{ $tender->invitations->count() }}</span>
</h2>
<p class="section-subtitle">Los participantes de esta solicitud.</p>
</div>
@can('update', $tender)<a href="{{ route('tenders.suppliers', $tender) }}" class="btn btn-outline-primary btn-sm">
<x-icon name="plus"/>Invitar</a>
@endcan</div>
@if($tender->invitations->isEmpty())<div class="empty-state compact">
<x-icon name="users"/>
<h3>Sumá a tus proveedores</h3>
<p>Elegí proveedores habilitados de tu organización para invitarlos.</p>
</div>
@else<div class="table-responsive">
<table class="table align-middle mb-0">
<thead>
<tr>
<th>Proveedor</th>
<th>Contacto</th>
<th>Invitación</th>
</tr>
</thead>
<tbody>
@foreach($tender->invitations as $invitation)<tr>
<td>
<strong>{{ $invitation->supplier->trade_name }}</strong>
@if($invitation->supplier->is_demo)<span class="demo-label">DEMO</span>
@endif</td>
<td>
<span class="small">{{ $invitation->contact?->name ?? 'Sin contacto seleccionado' }}</span>
</td>
<td>
<span class="neutral-badge">{{ config('ui.invitations.'.$invitation->status->value) }}</span>
</td>
</tr>
@endforeach</tbody>
</table>
</div>
@endif</section>
</div>
<div class="col-xl-4">
<aside class="card summary-card">
<h2 class="section-title mb-4">Resumen</h2>
<div class="summary-row">
<span>Fecha límite</span>
<strong>{{ $tender->deadline_at->setTimezone(config('bidding.display_timezone'))->format('d/m/Y · H:i') }} hs</strong>
<small>Buenos Aires · UTC−3</small>
</div>
<div class="summary-row">
<span>Moneda</span>
<strong>ARS · Peso argentino</strong>
</div>
<div class="summary-row">
<span>Creada el</span>
<strong>{{ $tender->created_at->setTimezone(config('bidding.display_timezone'))->format('d/m/Y') }}</strong>
</div>
@if($tender->status->value === 'draft')<hr>
<h3 class="mini-heading">Antes de publicar</h3>
<ul class="readiness">
<li @class(['ready' => $tender->items->isNotEmpty()])>
<x-icon name="check"/>Agregar ítems</li>
<li @class(['ready' => $tender->invitations->isNotEmpty()])>
<x-icon name="check"/>Invitar proveedores</li>
<li @class(['ready' => $tender->deadline_at->isFuture()])>
<x-icon name="check"/>Fecha límite futura</li>
</ul>
@can('update', $tender)<a href="{{ route('tenders.confirm', $tender) }}" class="btn btn-primary w-100 justify-content-center">Revisar y publicar</a>
@endcan @endif</aside>
</div>
</div>
@endsection
