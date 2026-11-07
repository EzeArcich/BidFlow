
@if(session('success'))<div class="alert alert-success d-flex gap-2" role="status">
<x-icon name="check"/>
<span>{{ session('success') }}</span>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger" role="alert" tabindex="-1" id="form-errors">
<strong>No pudimos completar la operación.</strong>
<ul class="mb-0 mt-2">
@foreach($errors->all() as $error)<li>{{ __($error) }}</li>
@endforeach</ul>
</div>
@endif
