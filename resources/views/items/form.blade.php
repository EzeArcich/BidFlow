
@extends('layouts.admin')
@section('title', $item ? 'Editar ítem' : 'Agregar ítem')
@section('content')
<div class="page-heading">
<div>
<a class="back-link" href="{{ route('tenders.show', $tender) }}">← {{ $tender->title }}</a>
<h1>{{ $item ? 'Editar ítem' : '¿Qué necesitás cotizar?' }}</h1>
<p>Detallá un producto, servicio o componente de la obra.</p>
</div>
</div>
<form method="post" action="{{ $item ? route('items.update', [$tender, $item->id]) : route('items.store', $tender) }}" class="card form-card form-narrow">
@csrf @if($item) @method('PATCH') @endif<div class="mb-4">
<label class="form-label" for="description">Descripción <span class="required">*</span>
</label>
<textarea id="description" name="description" class="form-control" rows="3" required maxlength="10000" placeholder="Ej.: Mantenimiento preventivo de equipos de climatización">{{ old('description', $item?->description) }}</textarea>
</div>
<div class="row g-3 mb-4">
<div class="col-md-6">
<label class="form-label" for="quantity">Cantidad <span class="required">*</span>
</label>
<input id="quantity" name="quantity" type="number" step="0.001" min="0.001" max="999999999.999" class="form-control" value="{{ old('quantity', $item?->quantity ?? '1') }}" required>
</div>
<div class="col-md-6">
<label class="form-label" for="unit">Unidad de medida <span class="required">*</span>
</label>
<input id="unit" name="unit" class="form-control" value="{{ old('unit', $item?->unit) }}" list="units" required maxlength="50" placeholder="Ej.: unidad, hora, kg, m²">
<datalist id="units">
<option value="unidad">
<option value="hora">
<option value="servicio">
<option value="kg">
<option value="m²">
<option value="mes">
</datalist>
</div>
</div>
<div class="mb-4">
<label class="form-label" for="specifications_text">Especificaciones</label>
<textarea id="specifications_text" name="specifications_text" class="form-control" rows="4" maxlength="10000" placeholder="Medidas, materiales, requisitos técnicos o entregables.">{{ old('specifications_text', is_string(data_get($item?->attributes, 'specifications')) ? data_get($item?->attributes, 'specifications') : '') }}</textarea>
</div>
<div class="mb-4">
<label class="form-label" for="reference_code">Código de referencia <span class="optional">opcional</span>
</label>
<input id="reference_code" name="reference_code" class="form-control" maxlength="255" value="{{ old('reference_code', $item?->reference_code) }}">
</div>
<div class="mb-4">
<label class="form-label" for="notes">Observaciones</label>
<textarea id="notes" name="notes" class="form-control" rows="2" maxlength="10000">{{ old('notes', $item?->notes) }}</textarea>
</div>
<div class="form-actions">
<a href="{{ route('tenders.show', $tender) }}" class="btn btn-outline-secondary">Cancelar</a>
<button type="submit" class="btn btn-primary">{{ $item ? 'Guardar cambios' : 'Agregar ítem' }}<x-icon name="check"/>
</button>
</div>
</form>
@endsection
