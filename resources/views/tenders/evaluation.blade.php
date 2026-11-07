
@extends('layouts.admin')
@section('title','Evaluación y adjudicación')
@section('content')
<a href="{{ route('tenders.show',$tender) }}" class="back-link">← {{ $tender->title }}</a>
<div class="page-heading">
<div>
<div class="eyebrow">COMPARACIÓN POR ÍTEM Y PROVEEDOR</div>
<h1>Evaluar y adjudicar</h1>
<p>Compará cantidades, precios y plazos. La decisión es de tu organización.</p>
</div>
<x-status :status="$tender->status"/>
</div>
@if($tender->status->value==='published')
@can('manage',$tender)<form method="post" action="{{ route('tenders.close',$tender) }}" class="notice">
@csrf<div>
<label>
<input type="checkbox" name="confirmation" value="1" required> Confirmo el cierre de recepción. Ya no se aceptarán nuevas ofertas.</label>
<button class="btn btn-primary btn-sm ms-3">Cerrar recepción</button>
</div>
</form>
@endcan @endif
<section class="card mb-4">
<div class="card-header">
<h2 class="section-title">Matriz comparativa · ARS</h2>
</div>
<div class="table-responsive">
<table class="table align-middle">
<thead>
<tr>
<th>Ítem / cantidades</th>
@foreach($evaluation['bids'] as $bid)<th>{{ $bid['supplier_name'] }}<small class="d-block">Total ARS {{ $bid['total_amount'] }}{{ $bid['partial']?' · Oferta parcial':'' }}</small>
</th>
@endforeach</tr>
</thead>
<tbody>
@foreach($evaluation['items'] as $item)<tr>
<td>
<strong>{{ $item['description'] }}</strong>
<small class="d-block">Solicitada: {{ $item['requested_quantity'] }} {{ $item['unit'] }}</small>
<small class="d-block">Adjudicada: {{ $item['awarded_quantity'] }}</small>
<strong class="d-block text-primary">Pendiente: {{ $item['pending_quantity'] }}</strong>
@if(!$item['quotes'])<span class="text-secondary small">Sin cotizaciones</span>
@endif</td>
@foreach($evaluation['bids'] as $bid)
@php($quote=collect($item['quotes'])->firstWhere('bid_id',$bid['id']))<td>
@if($quote)<strong>ARS {{ $quote['unit_price'] }} / unidad</strong>
<small class="d-block">Cantidad: {{ $quote['quantity'] }} · Disponible: {{ $quote['available_quantity'] }}</small>
<small class="d-block">Subtotal: ARS {{ $quote['subtotal'] }}</small>
<small class="d-block">Plazo: {{ $quote['delivery_days']??'—' }} días</small>
<small class="d-block">{{ $quote['conditions'] }}</small>
@else<span class="text-secondary">No cotizó</span>
@endif</td>
@endforeach</tr>
@endforeach</tbody>
</table>
</div>
</section>
@if(in_array($tender->status->value,['closed','partially_awarded']))
@can('manage',$tender)<form method="post" action="{{ route('tenders.allocate',$tender) }}" class="card form-card mb-4">
@csrf<input type="hidden" name="request_key" value="{{ old('request_key',(string) \Illuminate\Support\Str::uuid()) }}">
<h2 class="section-title mb-3">Nueva adjudicación</h2>
<p class="text-secondary small">Seleccioná las líneas y cantidades a adjudicar. Podés combinar proveedores y dejar cantidades pendientes.</p>
@php($row=0)<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th>Seleccionar / ítem</th>
<th>Proveedor</th>
<th>Cantidad</th>
<th>Precio unitario ARS</th>
<th>Observaciones</th>
</tr>
</thead>
<tbody>
@foreach($evaluation['items'] as $item)
@foreach($item['quotes'] as $quote)
@if(bccomp($item['pending_quantity'],'0',3)>0 && bccomp($quote['available_quantity'],'0',3)>0)
@php($oldLine=collect(old('lines',[]))->firstWhere('bid_item_id',$quote['bid_item_id']))
<tr>
<td>
<label>
<input type="checkbox" name="lines[{{ $row }}][select]" value="1" @checked($oldLine)> {{ $item['description'] }}</label>
<input type="hidden" name="lines[{{ $row }}][bid_item_id]" value="{{ $quote['bid_item_id'] }}">
</td>
<td>{{ $quote['supplier_name'] }}</td>
<td>
<input class="form-control" type="number" step="0.001" min="0.001" name="lines[{{ $row }}][quantity]" value="{{ $oldLine['quantity']??(bccomp($item['pending_quantity'],$quote['available_quantity'],3)<0?$item['pending_quantity']:$quote['available_quantity']) }}" aria-label="Cantidad para {{ $quote['supplier_name'] }}">
<small>Máximo ítem: {{ $item['pending_quantity'] }} / oferta: {{ $quote['available_quantity'] }}</small>
</td>
<td>
<input class="form-control" type="number" step="0.01" min="0" name="lines[{{ $row }}][unit_price]" value="{{ $oldLine['unit_price']??$quote['unit_price'] }}" aria-label="Precio adjudicado">
</td>
<td>
<input class="form-control" name="lines[{{ $row }}][notes]" value="{{ $oldLine['notes']??'' }}" aria-label="Observaciones de adjudicación">
</td>
</tr>
@php($row++)
@endif @endforeach @endforeach</tbody>
</table>
</div>
<label for="reason" class="form-label mt-3">Motivo o justificación</label>
<textarea id="reason" class="form-control mb-3" name="reason" required maxlength="10000">{{ old('reason') }}</textarea>
<label class="mb-3">
<input type="checkbox" name="confirmation" value="1" required> Confirmo las cantidades y condiciones seleccionadas. La adjudicación no se podrá editar.</label>
<button class="btn btn-primary align-self-start">Confirmar adjudicación y emitir órdenes</button>
</form>
@endcan @endif
<section class="card form-card mb-4">
<h2 class="section-title">Historial de adjudicaciones</h2>
@forelse($tender->awards as $award)<article class="border-bottom py-3">
<h3 class="h6">{{ $award->awarded_at->format('d/m/Y H:i') }} · ARS {{ $award->total_amount }}</h3>
<p>{{ $award->reason }}</p>
@foreach($award->lines as $line)<p class="small mb-1">{{ $line->snapshot['description']??$line->tender_item_id }} · {{ $line->snapshot['supplier_name']??$line->supplier_id }} · {{ $line->quantity }} × ARS {{ $line->unit_price }} = ARS {{ $line->subtotal }}</p>
@endforeach</article>
@empty<p class="text-secondary mt-3">Sin adjudicaciones confirmadas.</p>
@endforelse</section>
<section class="card form-card">
<h2 class="section-title">Órdenes por proveedor</h2>
@forelse($tender->workOrders as $order)<article class="border-bottom py-3">
<strong>{{ $order->number }}</strong>
<span class="neutral-badge ms-2">{{ $order->status->value }}</span>
@foreach($order->lines as $line)<p class="small mt-2 mb-0">{{ $line->snapshot['description']??'' }} · {{ $line->quantity }} · ARS {{ $line->subtotal }}</p>
@endforeach</article>
@empty<p class="text-secondary mt-3">No hay órdenes emitidas.</p>
@endforelse</section>
@endsection
