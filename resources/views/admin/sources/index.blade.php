<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Sources') }}</h2>
            @can('sources.create')
                <a href="{{ route('admin.sources.create') }}" class="btn btn-dark btn-sm">
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
                        <th>{{ __('Banque') }}</th>
                        <th>{{ __('Type de fichier') }}</th>
                        <th>{{ __('Devise') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sources as $source)
                        <tr>
                            <td><span class="font-monospace">{{ $source->code }}</span></td>
                            <td>{{ $source->name }}</td>
                            <td>{{ $source->bank?->name ?? '—' }}</td>
                            <td><span class="text-uppercase">{{ $source->file_type }}</span></td>
                            <td>{{ $source->defaultCurrency?->iso_code ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $source->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $source->is_active ? __('Actif') : __('Inactif') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('sources.update')
                                    <a href="{{ route('admin.sources.mappings.edit', $source) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Mapping des colonnes') }}">
                                        <i class="bi bi-diagram-2"></i>
                                    </a>
                                    <a href="{{ route('admin.sources.edit', $source) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @can('sources.delete')
                                    <form action="{{ route('admin.sources.destroy', $source) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Confirmer la suppression ?') }}')">
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
                            <td colspan="7" class="text-center text-secondary py-4">{{ __('Aucune source enregistrée.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sources->hasPages())
            <div class="card-footer">
                {{ $sources->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
