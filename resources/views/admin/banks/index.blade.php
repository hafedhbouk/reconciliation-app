<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Banques') }}</h2>
            @can('banks.create')
                <a href="{{ route('admin.banks.create') }}" class="btn btn-dark btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('Ajouter') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('SWIFT') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($banks as $bank)
                        <tr>
                            <td><span class="font-monospace">{{ $bank->code }}</span></td>
                            <td>{{ $bank->name }}</td>
                            <td>{{ $bank->swift_code ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $bank->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $bank->is_active ? __('Actif') : __('Inactif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('banks.update')
                                    <a href="{{ route('admin.banks.edit', $bank) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @can('banks.delete')
                                    <form action="{{ route('admin.banks.destroy', $bank) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Confirmer la suppression ?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-4">{{ __('Aucune banque enregistrée.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($banks->hasPages())
            <div class="card-footer">
                {{ $banks->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
