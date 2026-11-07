
@extends('layouts.admin')
@section('title', 'Publicar licitación')
@section('content')
<div class="page-heading">
<div>
<a class="back-link" href="{{ route('tenders.show', $tender) }}">← Volver a la licitación</a>
<div class="eyebrow mt-3">ÚLTIMO PASO</div>
<h1>Todo listo para avanzar.</h1>
<p>Revisá los datos de tu solicitud antes de publicarla.</p>
</div>
</div>
<div class="card form-card form-narrow">
<x-status :status="$tender->status"/>
<h2 class="mt-3 mb-4">{{ $tender->title }}</h2>
<div class="publish-facts">
<div>
<strong>{{ $tender->items->count() }}</strong>
<span>Ítems solicitados</span>
</div>
<div>
<strong>{{ $tender->invitations->count() }}</strong>
<span>Proveedores invitados</span>
</div>
<div>
<strong class="date-fact">{{ $tender->deadline_at->setTimezone(config('bidding.display_timezone'))->format('d/m/Y H:i') }}</strong>
<span>Fecha límite · Buenos Aires</span>
</div>
</div>
<h3 class="mini-heading mt-4">Proveedores seleccionados</h3>
<ul class="list-unstyled">
@forelse($tender->invitations as $invitation)<li class="py-2 border-bottom">{{ $invitation->supplier->trade_name }}</li>
@empty<li class="text-secondary">Todavía no hay proveedores invitados. <a href="{{ route('tenders.suppliers', $tender) }}">Seleccionar proveedores</a>
</li>
@endforelse</ul>
<div class="notice mt-4">
<x-icon name="clock"/>
<span>Al publicar, los datos de la solicitud quedan en modo de consulta. Las invitaciones se registran, pero todavía no se envían correos.</span>
</div>
<form method="post" action="{{ route('tenders.publish', $tender) }}">
@csrf<div class="form-check mb-4">
<input type="checkbox" id="confirm" name="confirmation" value="1" class="form-check-input" required>
<label for="confirm" class="form-check-label">Revisé los datos y quiero publicar esta licitación.</label>
</div>
<div class="form-actions">
<a class="btn btn-outline-secondary" href="{{ route('tenders.show', $tender) }}">Seguir editando</a>
<button class="btn btn-primary" type="submit">Confirmar publicación<x-icon name="arrow"/>
</button>
</div>
</form>
</div>
@endsection
