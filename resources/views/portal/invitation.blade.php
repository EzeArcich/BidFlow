
@extends('layouts.portal')
@section('title','Solicitud y oferta')
@section('content')
<a class="back-link" href="{{ route('portal.home') }}">← Mis invitaciones</a>
<div class="page-heading">
<div>
<div class="eyebrow">{{ request()->attributes->get('supplierOrganization')->name }}</div>
<h1>{{ $tender->title }}</h1>
<p>Fecha límite: {{ $tender->deadline_at->setTimezone(config('bidding.display_timezone'))->format('d/m/Y H:i') }} · Buenos Aires</p>
</div>
<x-status :status="$tender->status"/>
</div>
<section class="card form-card mb-4">
<p class="preserve-lines">{{ $tender->description }}</p>
@foreach($tender->conditions??[] as $key=>$value)<p>
<strong>{{ $key==='notes'?'Condiciones':$key }}:</strong> {{ is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE):$value }}</p>
@endforeach</section>
@php($editable=$tender->status->value==='published' && $tender->deadline_at->isFuture() && $invitation->expires_at->isFuture() && in_array($invitation->status->value,['pending','sent','viewed','responded']) && (!$bid || $bid->status->value==='draft'))
@if($editable)
<form method="post" action="{{ route('portal.bid.save',[$organization,$invitation->id]) }}" class="card form-card mb-4">
@csrf @method('PUT')<h2 class="section-title mb-3">Tu oferta · ARS</h2>
<p class="text-secondary small">Marcá los ítems que querés cotizar. Podés ofrecer una cantidad menor a la solicitada. Los precios son finales por unidad para esta cotización.</p>
<div class="table-responsive">
<table class="table align-middle">
<thead>
<tr>
<th>Cotizar / ítem</th>
<th>Cantidad ofrecida</th>
<th>Precio unitario ARS</th>
<th>Plazo (días)</th>
<th>Condiciones de la línea</th>
</tr>
</thead>
<tbody>
@foreach($tender->items as $index=>$item)
@php($saved=$bid?->items->firstWhere('tender_item_id',$item->id))
@php($oldLine=collect(old('lines',[]))->firstWhere('tender_item_id',$item->id))
<tr>
<td>
<label>
<input type="checkbox" name="lines[{{ $index }}][quote]" value="1" class="form-check-input me-2" @checked($oldLine || $saved)> {{ $item->description }}</label>
<small class="d-block text-secondary mt-2">Solicitado: {{ $item->quantity }} {{ $item->unit }}</small>
<input type="hidden" name="lines[{{ $index }}][tender_item_id]" value="{{ $item->id }}">
@foreach($item->attributes??[] as $key=>$value)<small class="d-block text-secondary">{{ $key }}: {{ is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE):$value }}</small>
@endforeach</td>
<td>
<input class="form-control" type="number" step="0.001" min="0.001" max="{{ $item->quantity }}" name="lines[{{ $index }}][quantity]" value="{{ $oldLine['quantity']??$saved?->quantity??$item->quantity }}" aria-label="Cantidad para {{ $item->description }}">
</td>
<td>
<input class="form-control" type="number" step="0.01" min="0" name="lines[{{ $index }}][unit_price]" value="{{ $oldLine['unit_price']??$saved?->unit_price??'0.00' }}" aria-label="Precio para {{ $item->description }}">
</td>
<td>
<input class="form-control" type="number" min="0" max="3650" name="lines[{{ $index }}][delivery_days]" value="{{ $oldLine['delivery_days']??$saved?->delivery_days }}" aria-label="Plazo para {{ $item->description }}">
</td>
<td>
<textarea class="form-control" name="lines[{{ $index }}][conditions]" aria-label="Condiciones para {{ $item->description }}">{{ $oldLine['conditions']??$saved?->conditions }}</textarea>
</td>
</tr>
@endforeach</tbody>
</table>
</div>
<label class="form-label mt-3" for="conditions">Condiciones generales</label>
<textarea class="form-control mb-3" id="conditions" name="conditions">{{ old('conditions',$bid?->conditions) }}</textarea>
<label class="form-label" for="warranty">Garantía</label>
<textarea class="form-control mb-3" id="warranty" name="warranty">{{ old('warranty',$bid?->warranty) }}</textarea>
<button class="btn btn-primary align-self-start">Guardar borrador</button>
</form>
@endif
@if($bid)<section class="card form-card mb-4">
<h2 class="section-title">Mi oferta · {{ $bid->status->value==='draft'?'Borrador':'Presentada' }}</h2>
<div class="table-responsive mt-3">
<table class="table">
<thead>
<tr>
<th>Ítem</th>
<th>Cantidad</th>
<th>Precio ARS</th>
<th>Subtotal ARS</th>
<th>Plazo</th>
</tr>
</thead>
<tbody>
@foreach($bid->items as $line)<tr>
<td>{{ $line->description }}</td>
<td>{{ $line->quantity }}</td>
<td>{{ $line->unit_price }}</td>
<td>{{ $line->subtotal }}</td>
<td>{{ $line->delivery_days??'—' }}</td>
</tr>
@endforeach</tbody>
</table>
</div>
<strong class="text-end mt-3">Total ofertado: ARS {{ $bid->total_amount }}</strong>
@if($editable)<form method="post" action="{{ route('portal.bid.submit',[$organization,$invitation->id]) }}" class="mt-4">
@csrf<div class="form-check mb-3">
<input id="confirm-bid" name="confirmation" value="1" type="checkbox" class="form-check-input" required>
<label for="confirm-bid">Confirmo que revisé esta oferta. Una vez presentada no podré modificarla.</label>
</div>
<button class="btn btn-primary">Presentar oferta definitivamente</button>
</form>
@else<p class="small text-secondary mt-3">La oferta y sus condiciones históricas se conservan en modo de consulta.</p>
@endif</section>
@endif
<section class="card form-card mb-4">
<h2 class="section-title mb-3">Mis adjudicaciones</h2>
@forelse($awards as $line)<div class="border-bottom py-3">
<strong>{{ $line->snapshot['description']??$line->tender_item_id }}</strong>
<p class="mb-1">{{ $line->quantity }} unidades · ARS {{ $line->unit_price }} por unidad · Total ARS {{ $line->subtotal }}</p>
<small class="text-secondary">{{ $line->award->awarded_at->format('d/m/Y') }} · {{ $line->award_id }}</small>
</div>
@empty<p class="text-secondary">Todavía no recibiste adjudicaciones para esta solicitud.</p>
@endforelse</section>
<section class="card form-card">
<h2 class="section-title mb-3">Mis órdenes</h2>
@forelse($orders as $order)<div class="border-bottom py-3">
<strong>{{ $order->number }}</strong>
<p>Estado: {{ $order->status->value }}</p>
@foreach($order->lines as $line)<p class="small">{{ $line->snapshot['description']??'' }} · {{ $line->quantity }} · ARS {{ $line->subtotal }}</p>
@endforeach</div>
@empty<p class="text-secondary">Sin órdenes emitidas.</p>
@endforelse</section>
@endsection
