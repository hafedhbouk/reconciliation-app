<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Exception') }} #{{ $exception->id }}</h2>
    </x-slot>

    <div class="row">
        <div class="col-md-7">
            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Type') }}</dt>
                        <dd class="col-sm-8">{{ $exception->type->label() }}</dd>

                        <dt class="col-sm-4">{{ __('Statut') }}</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $exception->status->badgeClass() }}">{{ $exception->status->label() }}</span>
                        </dd>

                        @if ($exception->normalizedTransaction)
                            <dt class="col-sm-4">{{ __('Source') }}</dt>
                            <dd class="col-sm-8">{{ $exception->normalizedTransaction->transaction?->source?->code }}</dd>

                            <dt class="col-sm-4">{{ __('Référence') }}</dt>
                            <dd class="col-sm-8">{{ $exception->normalizedTransaction->normalized_reference }}</dd>

                            <dt class="col-sm-4">{{ __('Montant') }}</dt>
                            <dd class="col-sm-8">{{ $exception->normalizedTransaction->normalized_amount_millimes }}</dd>

                            <dt class="col-sm-4">{{ __('Date') }}</dt>
                            <dd class="col-sm-8">{{ $exception->normalizedTransaction->normalized_date?->format('d/m/Y') }}</dd>
                        @endif

                        @if ($exception->matchingResult)
                            <dt class="col-sm-4">{{ __('Résultat de rapprochement') }}</dt>
                            <dd class="col-sm-8">
                                <a href="{{ route('admin.matching-results.show', $exception->matchingResult) }}">
                                    #{{ $exception->matchingResult->id }}
                                </a>
                            </dd>
                        @endif

                        <dt class="col-sm-4">{{ __('Assigné à') }}</dt>
                        <dd class="col-sm-8">{{ $exception->assignedTo?->name ?? '—' }}</dd>

                        @if ($exception->resolved_at)
                            <dt class="col-sm-4">{{ __('Résolu par') }}</dt>
                            <dd class="col-sm-8">{{ $exception->resolvedBy?->name }} — {{ $exception->resolved_at->format('d/m/Y H:i') }}</dd>
                        @endif

                        @if ($exception->resolution_comment)
                            <dt class="col-sm-4">{{ __('Commentaire') }}</dt>
                            <dd class="col-sm-8">{{ $exception->resolution_comment }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @can('exceptions.update')
                <div class="card mb-3">
                    <div class="card-header fw-semibold">{{ __('Mettre à jour') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.exceptions.update', $exception) }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <x-input-label for="status" :value="__('Statut')" />
                                <select id="status" name="status" class="form-select">
                                    @foreach (\App\Enums\ExceptionStatus::cases() as $case)
                                        <option value="{{ $case->value }}" @selected($exception->status === $case)>{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <x-input-label for="type" :value="__('Type')" />
                                <select id="type" name="type" class="form-select">
                                    @foreach (\App\Enums\ExceptionType::cases() as $case)
                                        <option value="{{ $case->value }}" @selected($exception->type === $case)>{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <x-input-label for="assigned_to" :value="__('Assigné à')" />
                                <select id="assigned_to" name="assigned_to" class="form-select">
                                    <option value="">{{ __('— Personne —') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected($exception->assigned_to === $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <x-input-label for="resolution_comment" :value="__('Commentaire')" />
                                <textarea id="resolution_comment" name="resolution_comment" class="form-control" rows="3">{{ old('resolution_comment', $exception->resolution_comment) }}</textarea>
                            </div>

                            <x-primary-button>{{ __('Enregistrer') }}</x-primary-button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header fw-semibold">{{ __('Pièces jointes') }}</div>
                <div class="list-group list-group-flush">
                    @forelse ($exception->attachments as $attachment)
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $attachment->original_name }}</span>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('admin.exceptions.attachments.download', [$exception, $attachment]) }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-download"></i>
                                </a>
                                @can('exceptions.update')
                                    <form action="{{ route('admin.exceptions.attachments.destroy', [$exception, $attachment]) }}" method="POST" onsubmit="return confirm('Confirmer la suppression ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary text-center py-3">{{ __('Aucune pièce jointe.') }}</div>
                    @endforelse
                </div>
                @can('exceptions.update')
                    <div class="card-body">
                        <form action="{{ route('admin.exceptions.attachments.store', $exception) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-2">
                                <input type="file" name="file" class="form-control form-control-sm" required>
                                <x-input-error :messages="$errors->get('file')" />
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-upload me-1"></i>{{ __('Ajouter') }}
                            </button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
