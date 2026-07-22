<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Paramètres') }}</h2>
    </x-slot>

    @foreach ($settings as $group => $groupSettings)
        <div class="card mb-3">
            <div class="card-header text-uppercase small fw-semibold">{{ $group }}</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Clé') }}</th>
                            <th>{{ __('Valeur') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupSettings as $setting)
                            <tr>
                                <td><span class="font-monospace">{{ $setting->key }}</span></td>
                                <td>{{ is_array($setting->value) ? json_encode($setting->value) : $setting->value }}</td>
                                <td class="small text-secondary">{{ $setting->description }}</td>
                                <td class="text-end">
                                    @can('settings.update')
                                        @if ($setting->is_editable)
                                            <a href="{{ route('admin.settings.edit', $setting) }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</x-app-layout>
