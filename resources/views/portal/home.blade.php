
@extends('layouts.portal')
@section('title','Mis invitaciones')
@section('content')
<div class="page-heading">
<div>
<div class="eyebrow">TU ESPACIO DE COTIZACIÓN</div>
<h1>Mis invitaciones</h1>
<p>Solicitudes dirigidas a tu proveedor. Tus ofertas y resultados están dentro de cada solicitud.</p>
</div>
</div>
<section class="card">
<div class="table-responsive">
<table class="table align-middle">
<thead>
<tr>
<th>Organización</th>
<th>Licitación</th>
<th>Estado</th>
<th>Fecha límite</th>
<th>
</th>
</tr>
</thead>
<tbody>
@forelse($invitations as $inv)<tr>
<td>{{ $inv['organization_name'] }}</td>
<td>{{ $inv['tender_title'] }}</td>
<td>
<x-status :status="$inv['tender_status']"/>
</td>
<td>{{ \Carbon\CarbonImmutable::parse($inv['deadline_at'])->setTimezone(config('bidding.display_timezone'))->format('d/m/Y H:i') }}</td>
<td>
<a class="btn btn-outline-primary btn-sm" href="{{ route('portal.invitation',[$inv['organization_id'],$inv['id']]) }}">Ver solicitud</a>
</td>
</tr>
@empty<tr>
<td colspan="5" class="text-center py-5 text-secondary">No hay invitaciones disponibles para tu proveedor.</td>
</tr>
@endforelse</tbody>
</table>
</div>
</section>
@endsection
