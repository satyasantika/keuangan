@if (config('services.google.aktif'))
    <div class="mt-4 text-center text-sm">
        @if (session('galat_google'))
            <p role="alert" class="mb-3 text-danger-600 dark:text-danger-400">{{ session('galat_google') }}</p>
        @endif
        <a href="{{ route('auth.google.redirect', ['asal' => \Livewire\Livewire::originalUrl()]) }}"
           class="inline-block w-full rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/20 dark:text-gray-200 dark:hover:bg-white/5">Masuk dengan Google</a>
    </div>
@endif
