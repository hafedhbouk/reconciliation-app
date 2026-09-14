<div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead><tr><th>{{ __('Catégorie') }}</th><th>{{ __('Lignes') }}</th><th>{{ __('Montant (millimes)') }}</th></tr></thead>
        <tbody>
            @foreach (['total' => 'Acceptées comparées', 'matched' => 'Rapprochées', 'conflict' => 'En conflit', 'exclusive' => 'Exclusives à ce fichier'] as $key => $label)
                <tr><td>{{ __($label) }}</td><td>{{ $totals[$key]['rows'] }}</td><td>{{ $totals[$key]['amount_millimes'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="small m-2 {{ $totals['balanced'] ? 'text-success' : 'text-danger' }}">
    {{ $totals['balanced'] ? __('Contrôle des lignes et des montants : équilibré.') : __('Écart de contrôle : vérifier les données.') }}
</p>
