@can('matching-rules.update')
    <form action="{{ route('admin.matching-rules.run', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('Lancer cette règle maintenant ?')">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Lancer') }}">
            <i class="bi bi-play-fill"></i>
        </button>
    </form>
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
