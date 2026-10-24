{{-- E-mail des résultats d'un rapprochement (App\Mail\MatchingResultsMail). Uniquement des chiffres, aucune ligne de données. --}}
<x-mail::message>
# Résultats du rapprochement

**{{ $summary['operation'] }}**

<x-mail::table>
| | |
|:--|--:|
| Date et heure du rapprochement | {{ $summary['date'] ?? '—' }} |
| Statut final | {{ $summary['statut'] }} |
| {{ $summary['unite'] === 'lignes' ? 'Lignes traitées' : 'Clés (références) traitées' }} | {{ number_format($summary['traitees'], 0, ',', ' ') }} |
| Correspondances trouvées | {{ number_format($summary['trouvees'], 0, ',', ' ') }} |
| Correspondances manquantes | {{ $summary['manquantes'] === null ? 'non calculé' : number_format($summary['manquantes'], 0, ',', ' ') }} |
@foreach ($summary['manquantes_detail'] as $side => $count)
| — dont présentes seulement dans le {{ $side }} | {{ number_format($count, 0, ',', ' ') }} |
@endforeach
| Erreurs (conflits de montant ou de date) | {{ number_format($summary['erreurs'], 0, ',', ' ') }} |
</x-mail::table>

@if ($summary['lien'])
<x-mail::button :url="$summary['lien']">
Voir les résultats
</x-mail::button>

Une connexion à l’application est nécessaire pour consulter le détail.
@endif

@if ($file)
Le fichier des résultats est joint à ce message ({{ $file['name'] }}).
@endif
@if ($note)

{{ $note }}
@endif

Cordialement,<br>
{{ config('app.name') }}
</x-mail::message>
