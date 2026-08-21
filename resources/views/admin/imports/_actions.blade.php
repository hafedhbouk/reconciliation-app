<a href="{{ route('admin.imports.show', $import) }}" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-eye"></i>
</a>

<form method="POST" action="{{ route('admin.imports.destroy', $import) }}" class="d-inline" onsubmit="return confirm('{{ __('Êtes-vous sûr de vouloir supprimer cet import ?') }}');">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash"></i>
    </button>
</form>
