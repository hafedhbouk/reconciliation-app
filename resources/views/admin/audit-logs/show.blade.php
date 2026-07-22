<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Détail de l\'audit') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 48rem;">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('Date') }}</dt>
                <dd class="col-sm-9">{{ $log->created_at->format('d/m/Y H:i:s') }}</dd>

                <dt class="col-sm-3">{{ __('Utilisateur') }}</dt>
                <dd class="col-sm-9">{{ $log->user?->name ?? __('Système') }}</dd>

                <dt class="col-sm-3">{{ __('Événement') }}</dt>
                <dd class="col-sm-9"><span class="badge bg-secondary">{{ $log->event }}</span></dd>

                <dt class="col-sm-3">{{ __('Sujet') }}</dt>
                <dd class="col-sm-9">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</dd>

                <dt class="col-sm-3">{{ __('Adresse IP') }}</dt>
                <dd class="col-sm-9">{{ $log->ip_address ?? '—' }}</dd>

                <dt class="col-sm-3">{{ __('URL') }}</dt>
                <dd class="col-sm-9 text-break">{{ $log->url ?? '—' }}</dd>

                <dt class="col-sm-3">{{ __('Navigateur') }}</dt>
                <dd class="col-sm-9 text-break small">{{ $log->user_agent ?? '—' }}</dd>
            </dl>

            @if ($log->old_values)
                <h3 class="fs-6 fw-semibold mt-3">{{ __('Anciennes valeurs') }}</h3>
                <pre class="bg-body-tertiary p-2 rounded small">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif

            @if ($log->new_values)
                <h3 class="fs-6 fw-semibold mt-3">{{ __('Nouvelles valeurs') }}</h3>
                <pre class="bg-body-tertiary p-2 rounded small">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif

            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary mt-3">{{ __('Retour') }}</a>
        </div>
    </div>
</x-app-layout>
