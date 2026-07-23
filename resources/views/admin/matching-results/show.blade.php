<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Résultat de rapprochement') }} #{{ $result->id }}</h2>
    </x-slot>

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-2">{{ __('Règle') }}</dt>
                <dd class="col-sm-4">{{ $result->matchingRule?->name ?? __('Rapprochement manuel') }}</dd>

                <dt class="col-sm-2">{{ __('Statut') }}</dt>
                <dd class="col-sm-4">
                    <span class="badge {{ $result->status->badgeClass() }}">{{ $result->status->label() }}</span>
                </dd>

                <dt class="col-sm-2">{{ __('Confiance') }}</dt>
                <dd class="col-sm-4">{{ $result->confidence_score ?? '—' }}</dd>

                <dt class="col-sm-2">{{ __('Traité par') }}</dt>
                <dd class="col-sm-4">{{ $result->matchedByUser?->name ?? __('Automatique') }}</dd>

                <dt class="col-sm-2">{{ __('Lot') }}</dt>
                <dd class="col-sm-4">{{ $result->batch_reference ?? '—' }}</dd>

                @if ($result->notes)
                    <dt class="col-sm-2">{{ __('Notes') }}</dt>
                    <dd class="col-sm-10 text-warning-emphasis">{{ $result->notes }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <div class="row">
        @foreach (['a' => __('Côté A'), 'b' => __('Côté B')] as $side => $label)
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header fw-semibold">{{ $label }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Source') }}</th>
                                    <th>{{ __('Référence') }}</th>
                                    <th>{{ __('Montant') }}</th>
                                    <th>{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($result->matchingDetails->where('side', $side) as $detail)
                                    @php $nt = $detail->normalizedTransaction; @endphp
                                    <tr>
                                        <td>{{ $nt?->transaction?->source?->code }}</td>
                                        <td>{{ $nt?->normalized_reference }}</td>
                                        <td>{{ $nt?->normalized_amount_millimes }}</td>
                                        <td>{{ $nt?->normalized_date?->format('d/m/Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-3">{{ __('Aucune transaction.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($result->exceptions->isNotEmpty())
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Exceptions liées') }}</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Statut') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result->exceptions as $exception)
                            <tr>
                                <td>{{ $exception->type->label() }}</td>
                                <td><span class="badge {{ $exception->status->badgeClass() }}">{{ $exception->status->label() }}</span></td>
                                <td>
                                    <a href="{{ route('admin.exceptions.show', $exception) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
