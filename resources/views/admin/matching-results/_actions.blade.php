{{-- Actions disponibles pour chaque ligne de résultat de rapprochement : consultation et suppression --}}
<a href="{{ route('admin.matching-results.show', $result) }}" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-eye"></i>
</a>

<form method="POST" action="{{ route('admin.matching-results.destroy', $result) }}" class="d-inline" onsubmit="return confirm('{{ __('Êtes-vous sûr de vouloir supprimer ce résultat de rapprochement ?') }}');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash"></i>
    </button>
</form>
