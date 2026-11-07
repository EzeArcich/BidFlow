
@extends('layouts.admin')
@section('title', $tender ? 'Invitar proveedores' : 'Proveedores')
@section('content')
<div class="page-heading">
<div>
@if($tender)<a class="back-link" href="{{ route('tenders.show', $tender) }}">← {{ $tender->title }}</a>
@else<div class="eyebrow">TU RED DE COLABORACIÓN</div>
@endif<h1>{{ $tender ? 'Elegí a quién invitar.' : 'Proveedores' }}</h1>
<p>Proveedores activos y habilitados para tu organización.</p>
</div>
@if($tender)<span class="step-label">02 / SELECCIONAR PROVEEDORES</span>
@endif</div>
<section class="card">
<form method="get" class="filter-bar">
<div class="search-field">
<label for="q" class="visually-hidden">Buscar proveedor</label>
<x-icon name="search"/>
<input id="q" name="q" class="form-control" value="{{ request('q') }}" placeholder="Buscar por nombre o razón social..." maxlength="200">
</div>
<button class="btn btn-outline-secondary">Buscar</button>
@if(request('q'))<a href="{{ $tender ? route('tenders.suppliers', $tender) : route('suppliers.index') }}" class="text-link small">Limpiar</a>
@endif</form>
@if($tender)<form method="post" action="{{ route('invitations.store', $tender) }}" id="supplier-selection">
@csrf @endif
@if($suppliers->isEmpty())<div class="empty-state">
<x-icon name="users"/>
<h3>No encontramos proveedores</h3>
<p>Probá con otra búsqueda. Sólo se muestran proveedores activos y vinculados a tu organización.</p>
</div>
@else<div class="table-responsive">
<table class="table align-middle mb-0">
<thead>
<tr>
@if($tender)<th scope="col">
<span class="visually-hidden">Seleccionar</span>
</th>
@endif<th scope="col">Proveedor</th>
<th scope="col">Capacidades</th>
<th scope="col">Contacto</th>
</tr>
</thead>
<tbody>
@foreach($suppliers as $index => $supplier)
@php($alreadyInvited = in_array($supplier->id, $invited))<tr>
@if($tender)<td class="selection-cell">
@if($alreadyInvited)<span class="text-success" title="Ya invitado">
<x-icon name="check"/>
</span>
@else<input class="form-check-input supplier-check" type="checkbox" name="invitations[{{ $index }}][supplier_id]" value="{{ $supplier->id }}" id="supplier-{{ $index }}" data-contact="contact-{{ $index }}" @checked(data_get(old('invitations', []), $index.'.supplier_id') === $supplier->id) aria-label="Invitar a {{ $supplier->trade_name }}">
@endif</td>
@endif<td>
<strong class="table-title">{{ $supplier->trade_name }}</strong>
<div class="small text-secondary mt-1">{{ $supplier->legal_name }}</div>
@if($supplier->is_demo)<span class="demo-label">DEMO</span>
@endif @if($tender && $alreadyInvited)<span class="neutral-badge ms-1">Ya invitado</span>
@endif</td>
<td>
<div class="d-flex gap-1 flex-wrap">
@forelse($supplier->capabilities as $capability)<span class="category-label">{{ $capability->capability }}</span>
@empty<span class="text-secondary small">Sin especialidades indicadas</span>
@endforelse</div>
</td>
<td>
@if($tender && !$alreadyInvited)<label class="visually-hidden" for="contact-{{ $index }}">Contacto de {{ $supplier->trade_name }}</label>
<select id="contact-{{ $index }}" name="invitations[{{ $index }}][contact_id]" class="form-select form-select-sm supplier-contact" disabled>
<option value="">Sin contacto específico</option>
@foreach($supplier->contacts as $contact)<option value="{{ $contact->id }}" @selected(data_get(old('invitations', []), $index.'.contact_id') === $contact->id)>{{ $contact->name }} · {{ $contact->email }}</option>
@endforeach</select>
@else @foreach($supplier->contacts as $contact)<div class="small">{{ $contact->name }}<span class="d-block text-secondary">{{ $contact->email }}</span>
</div>
@endforeach @endif</td>
</tr>
@endforeach</tbody>
</table>
</div>
@endif
@if($tender)<div class="selection-actions">
<span>
<strong id="selection-count">0</strong> proveedores seleccionados en esta página</span>
<button class="btn btn-primary" type="submit">
<x-icon name="plus"/>Guardar invitaciones</button>
</div>
</form>
@endif<div class="card-footer">{{ $suppliers->links() }}</div>
</section>
@if(!$tender)<div class="notice mt-4">
<x-icon name="file"/>
<span>Para invitar a un proveedor, abrí una licitación en borrador y elegí «Invitar».</span>
</div>
@endif
@endsection
