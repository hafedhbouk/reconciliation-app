@props([
    'name',
    'show' => false,
])

<div
    id="{{ $name }}"
    class="modal fade"
    tabindex="-1"
    aria-hidden="true"
    {{ $attributes }}
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>

@if ($show)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new bootstrap.Modal(document.getElementById(@json($name))).show();
        });
    </script>
@endif
