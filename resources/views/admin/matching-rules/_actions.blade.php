@can('matching-rules.update')
    @php
        $importsA = $rule->sourceA->imports()->where('status', 'completed')->orderByDesc('created_at')->get();
        $importsB = $rule->sourceB->imports()->where('status', 'completed')->orderByDesc('created_at')->get();
    @endphp

    @if($importsA->isNotEmpty() && $importsB->isNotEmpty())
        <form action="{{ route('admin.matching-rules.run', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('Lancer cette règle maintenant ?')">
            @csrf
            <input type="hidden" name="import_a_id" value="{{ old('import_a_id', $importsA->first()->id) }}">
            <input type="hidden" name="import_b_id" value="{{ old('import_b_id', $importsB->first()->id) }}">
            <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Lancer') }}">
                <i class="bi bi-play-fill"></i>
            </button>
        </form>
    @else
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="{{ __('Aucun import disponible') }}">
            <i class="bi bi-play-fill"></i>
        </button>
    @endif

    <a href="{{ route('admin.matching-rules.edit', $rule) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-pencil"></i>
    </a>
@endcan
@can('matching-rules.delete')
    <form action="{{ route('admin.matching-rules.destroy', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirmer la suppression ?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-trash"></i>
        </button>
    </form>
@endcan
