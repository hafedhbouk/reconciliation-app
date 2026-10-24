{{--
    Choix des destinataires des résultats par e-mail (formulaire « Comparer deux fichiers » et page d'envoi).
    $recipients : comptes actifs autorisés à consulter les résultats (ResultEmails::authorizedRecipients).
    $canAttach  : l'utilisateur peut demander le fichier des résultats en pièce jointe.
--}}
<div class="row g-2">
    <div class="col-md-8">
        <label for="email_destinataires" class="form-label small fw-semibold">{{ __('Destinataires') }}</label>
        <select name="email_destinataires[]" id="email_destinataires" multiple size="{{ min(6, max(3, $recipients->count())) }}"
                class="form-select form-select-sm @error('email_destinataires') is-invalid @enderror @error('email_destinataires.*') is-invalid @enderror">
            @foreach ($recipients as $recipient)
                <option value="{{ $recipient->id }}" @selected(in_array($recipient->id, old('email_destinataires', [auth()->id()])))>
                    {{ $recipient->name }} — {{ $recipient->email }}{{ $recipient->id === auth()->id() ? ' ('.__('moi').')' : '' }}
                </option>
            @endforeach
        </select>
        @error('email_destinataires')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('email_destinataires.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">{{ __('Seuls les comptes actifs autorisés à consulter les résultats sont proposés. Ctrl + clic pour en choisir plusieurs.') }}</div>
    </div>
    <div class="col-md-4">
        @if ($canAttach)
            <div class="form-check mt-md-4">
                <input type="hidden" name="email_piece_jointe" value="0">
                <input class="form-check-input" type="checkbox" name="email_piece_jointe" value="1" id="email_piece_jointe" @checked(old('email_piece_jointe'))>
                <label class="form-check-label small" for="email_piece_jointe">{{ __('Joindre le fichier des résultats') }}</label>
            </div>
            <div class="form-text">{{ __('Excel jusqu’à 1 000 lignes, CSV au-delà ; non joint au-delà de 10 Mo. Seuls les destinataires ayant accès aux exports le reçoivent.') }}</div>
        @else
            <div class="form-text mt-md-4">{{ __('Le corps de l’e-mail ne contient que les chiffres clés et un lien vers l’application.') }}</div>
        @endif
    </div>
</div>
