<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Devises') }}</h2>
            @can('currencies.create')
                <a href="{{ route('admin.currencies.create') }}" class="btn btn-dark btn-sm">
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
                        <th>{{ __('Code ISO') }}</th>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('Symbole') }}</th>
                        <th>{{ __('Décimales') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($currencies as $currency)
                        <tr>
                            <td><span class="font-monospace">{{ $currency->iso_code }}</span></td>
                            <td>{{ $currency->name }}</td>
                            <td>{{ $currency->symbol ?? '—' }}</td>
                            <td>{{ $currency->decimal_places }}</td>
                            <td>
                                <span class="badge {{ $currency->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $currency->is_active ? __('Actif') : __('Inactif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('currencies.update')
                                    <a href="{{ route('admin.currencies.edit', $currency) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @can('currencies.delete')
                                    <form action="{{ route('admin.currencies.destroy', $currency) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Confirmer la suppression ?') }}')">
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
                            <td colspan="6" class="text-center text-secondary py-4">{{ __('Aucune devise enregistrée.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($currencies->hasPages())
            <div class="card-footer">
                {{ $currencies->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
