@props(['status'])
@php($value = $status instanceof \BackedEnum ? $status->value : $status)
<span class="status-badge status-{{ $value }}">
<span class="status-dot">
</span>{{ config('ui.statuses.'.$value, $value) }}</span>
