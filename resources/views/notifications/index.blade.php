<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">{{ __('Notifications') }}</h2>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Tout marquer comme lu') }}</button>
            </form>
        </div>
    </x-slot>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <div class="list-group-item d-flex justify-content-between align-items-start {{ $notification->read_at ? '' : 'bg-body-tertiary' }}">
                    <div>
                        @if ($notification->type === \App\Notifications\ImportProcessedNotification::class)
                            <div class="fw-semibold">
                                {{ __('Import :source terminé', ['source' => $notification->data['source_code'] ?? '?']) }}
                            </div>
                            <div class="small text-secondary">
                                {{ $notification->data['original_filename'] ?? '' }} —
                                {{ __(':success réussies, :error en erreur', ['success' => $notification->data['success_rows'] ?? 0, 'error' => $notification->data['error_rows'] ?? 0]) }}
                            </div>
                        @elseif ($notification->type === \App\Notifications\MatchingActionCompletedNotification::class)
                            <div class="fw-semibold">{{ $notification->data['title'] ?? '' }}</div>
                            <div class="small text-secondary">
                                {{ implode(' — ', $notification->data['lines'] ?? []) }}
                            </div>
                        @else
                            <div class="fw-semibold">{{ __('Notification') }}</div>
                        @endif
                        <div class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</div>
                    </div>
                    @unless ($notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Marquer comme lu') }}</button>
                        </form>
                    @endunless
                </div>
            @empty
                <div class="list-group-item text-secondary text-center py-4">{{ __('Aucune notification.') }}</div>
            @endforelse
        </div>
        @if ($notifications->hasPages())
            <div class="card-footer">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
