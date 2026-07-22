@can('users.update')
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-pencil"></i>
    </a>
@endcan
@can('users.delete')
    @if ($user->id !== auth()->id())
        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirmer la suppression ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    @endif
@endcan
