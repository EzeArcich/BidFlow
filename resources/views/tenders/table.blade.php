
@if($tenders->isEmpty())<div class="empty-state">
<span class="empty-icon">
<x-icon name="file"/>
</span>
<h3>No hay licitaciones para mostrar</h3>
<p>Creá una nueva solicitud o ajustá los filtros de búsqueda.</p>
@can('create', \App\Domain\Tenders\Models\Tender::class)<a href="{{ route('tenders.create') }}" class="btn btn-primary btn-sm">Crear licitación</a>
@endcan</div>
@else<div class="table-responsive">
<table class="table align-middle mb-0">
<thead>
<tr>
<th scope="col">Licitación</th>
<th scope="col">Categoría</th>
<th scope="col">Estado</th>
<th scope="col">Fecha límite</th>
<th scope="col">
<span class="visually-hidden">Acciones</span>
</th>
</tr>
</thead>
<tbody>
@foreach($tenders as $entry)<tr>
<td>
<a class="table-title" href="{{ route('tenders.show', $entry) }}">{{ $entry->title }}</a>
<div class="small text-secondary mt-1">{{ $entry->external_reference ?: 'Sin referencia externa' }} @if($entry->is_demo)<span class="demo-label">DEMO</span>
@endif</div>
</td>
<td>
<span class="category-label">{{ config('ui.types.'.$entry->type->value) }}</span>
</td>
<td>
<x-status :status="$entry->status"/>
</td>
<td class="date-cell">{{ $entry->deadline_at->setTimezone(config('bidding.display_timezone'))->format('d/m/Y') }}<small>{{ $entry->deadline_at->setTimezone(config('bidding.display_timezone'))->format('H:i') }} hs</small>
</td>
<td class="text-end">
<a class="btn btn-light btn-sm" href="{{ route('tenders.show', $entry) }}" aria-label="Ver {{ $entry->title }}">
<x-icon name="arrow"/>
</a>
</td>
</tr>
@endforeach</tbody>
</table>
</div>
@endif
