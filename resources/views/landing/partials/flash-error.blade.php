    @if (session('error'))
        <div class="fixed inset-x-0 top-20 z-50 mx-auto max-w-3xl px-4">
            <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-lg">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" />
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif
