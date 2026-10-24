{{--
    Envoi des résultats d'un rapprochement terminé par e-mail, et historique des envois.
    Données : OperationEmailController::create ; envoi : App\Services\ResultEmails.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Envoyer les résultats par e-mail') }}</h2>
            <a href="{{ route('admin.operations.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Mes opérations') }}</a>
        </div>
    </x-slot>

    @if (session('error'))
        <div class="alert alert-warning">{{ session('error') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Contenu de l’e-mail') }}</div>
                <div class="card-body">
                    <p class="fw-semibold mb-2">{{ $summary['operation'] }}</p>
                    <dl class="row small mb-0">
                        <dt class="col-7">{{ __('Date et heure du rapprochement') }}</dt><dd class="col-5 text-end">{{ $summary['date'] ?? '—' }}</dd>
                        <dt class="col-7">{{ __('Statut final') }}</dt><dd class="col-5 text-end">{{ $summary['statut'] }}</dd>
                        <dt class="col-7">{{ $summary['unite'] === 'lignes' ? __('Lignes traitées') : __('Clés (références) traitées') }}</dt><dd class="col-5 text-end">{{ number_format($summary['traitees'], 0, ',', ' ') }}</dd>
                        <dt class="col-7">{{ __('Correspondances trouvées') }}</dt><dd class="col-5 text-end">{{ number_format($summary['trouvees'], 0, ',', ' ') }}</dd>
                        <dt class="col-7">{{ __('Correspondances manquantes') }}</dt><dd class="col-5 text-end">{{ $summary['manquantes'] === null ? __('non calculé') : number_format($summary['manquantes'], 0, ',', ' ') }}</dd>
                        <dt class="col-7">{{ __('Erreurs (conflits)') }}</dt><dd class="col-5 text-end">{{ number_format($summary['erreurs'], 0, ',', ' ') }}</dd>
                    </dl>
                    <p class="small text-secondary mt-3 mb-0">{{ __('L’e-mail contient ces chiffres et un lien vers les résultats (connexion obligatoire), jamais le détail des lignes dans le message.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ __('Destinataires') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.operations.email.store', $operation) }}">
                        @csrf
                        @include('admin.operations._email-recipients', ['recipients' => $recipients, 'canAttach' => $canAttach])
                        <button type="submit" class="btn btn-primary btn-sm mt-3"><i class="bi bi-envelope me-1"></i>{{ __('Envoyer') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header fw-semibold">{{ __('Historique des envois') }}</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Destinataire') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th>{{ __('Pièce jointe') }}</th>
                        <th>{{ __('Demandé par') }}</th>
                        <th>{{ __('Envoyé le') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $email)
                        <tr>
                            <td>{{ $email->recipient?->name ?? '—' }}<div class="small text-secondary">{{ $email->email }}</div></td>
                            <td>
                                <span class="badge bg-{{ $email->statusClass() }}">{{ $email->statusLabel() }}</span>
                                @if ($email->error_message)<div class="small text-danger">{{ $email->error_message }}</div>@endif
                                @if ($email->note)<div class="small text-secondary">{{ $email->note }}</div>@endif
                            </td>
                            <td class="small">{{ $email->attachment_name ?? ($email->with_attachment ? __('demandée') : '—') }}</td>
                            <td class="small">{{ $email->requester?->name ?? '—' }}</td>
                            <td class="small">{{ $email->sentAtLabel() ?? '—' }}</td>
                            <td>
                                @if ($email->status === 'failed')
                                    <form method="POST" action="{{ route('admin.result-emails.retry', $email) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Renvoyer') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-3">{{ __('Aucun envoi pour ce rapprochement.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
