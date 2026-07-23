<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ __('Nouvel import') }}</h2>
    </x-slot>

    <div class="card" style="max-width: 40rem;">
        <div class="card-body">
            @if (session('duplicate_warning'))
                <div class="alert alert-warning">{{ session('duplicate_warning') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data">
                @csrf

                @if (session('duplicate_warning'))
                    <input type="hidden" name="confirmed_duplicate" value="1">
                @endif

                <div class="mb-3">
                    <x-input-label for="source_id" :value="__('Source')" />
                    <select id="source_id" name="source_id" class="form-select" required>
                        <option value="">{{ __('— Choisir —') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected(old('source_id') == $source->id)>
                                {{ $source->code }} — {{ $source->name }} ({{ strtoupper($source->file_type) }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('source_id')" />
                </div>

                <div class="mb-3">
                    <x-input-label for="file" :value="__('Fichier')" />
                    <input type="file" id="file" name="file" class="form-control" required>
                    <x-input-error :messages="$errors->get('file')" />
                </div>

                <div class="d-flex gap-2">
                    <x-primary-button>{{ __('Importer') }}</x-primary-button>
                    <a href="{{ route('admin.imports.index') }}" class="btn btn-outline-secondary">{{ __('Annuler') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
