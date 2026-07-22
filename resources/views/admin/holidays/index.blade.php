<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Jours fériés') }}</h2>
            @can('holidays.create')
                <a href="{{ route('admin.holidays.create') }}" class="btn btn-dark btn-sm">
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
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Nom') }}</th>
                        <th>{{ __('Pays') }}</th>
                        <th>{{ __('Récurrent') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($holidays as $holiday)
                        <tr>
                            <td>{{ $holiday->holiday_date->format('d/m/Y') }}</td>
                            <td>{{ $holiday->name }}</td>
                            <td>{{ $holiday->country_code }}</td>
                            <td>
                                @if ($holiday->is_recurring_yearly)
                                    <span class="badge bg-info text-dark">{{ __('Oui') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('Non') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @can('holidays.update')
                                    <a href="{{ route('admin.holidays.edit', $holiday) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @can('holidays.delete')
                                    <form action="{{ route('admin.holidays.destroy', $holiday) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Confirmer la suppression ?') }}')">
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
                            <td colspan="5" class="text-center text-secondary py-4">{{ __('Aucun jour férié enregistré.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($holidays->hasPages())
            <div class="card-footer">
                {{ $holidays->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
