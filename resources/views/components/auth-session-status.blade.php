@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-success small fw-medium mb-3']) }}>
        {{ $status }}
    </div>
@endif
