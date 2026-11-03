<x-landing-auth-layout title="Panduan {{ $meta['judul'] }}">
    <div class="lg:grid lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-10 xl:grid-cols-[220px_minmax(0,1fr)_200px]">

        {{-- Sidebar: daftar panduan --}}
        <aside class="mb-6 lg:mb-0">
            <div class="lg:sticky lg:top-24">
                {{-- Mobile: dropdown --}}
                <details class="group rounded-xl border border-slate-200 bg-white p-1 lg:hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold text-auth-900">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-bars-3 class="h-4 w-4 text-slate-400" />
                            {{ $meta['judul'] }}
                        </span>
                        <x-heroicon-m-chevron-down class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                    </summary>
                    <nav class="mt-1 border-t border-slate-100 pt-1">
                        @foreach ($panduan as $slugPanduan => $item)
                            <a href="{{ route('panduan.show', $slugPanduan) }}"
                               @class([
                                   'block rounded-lg px-3 py-2 text-sm transition',
                                   'bg-auth-50 font-semibold text-auth-700' => $slugPanduan === $slug,
                                   'text-slate-600 hover:bg-slate-50' => $slugPanduan !== $slug,
                               ])>
                                {{ $item['judul'] }}
                            </a>
                        @endforeach
                    </nav>
                </details>

                {{-- Desktop: daftar vertikal --}}
                <nav class="hidden lg:block">
                    <p class="px-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">
                        Panduan
                    </p>
                    <ul class="mt-3 space-y-0.5 border-l border-slate-200">
                        @foreach ($panduan as $slugPanduan => $item)
                            <li>
                                <a href="{{ route('panduan.show', $slugPanduan) }}"
                                   @class([
                                       '-ml-px block border-l-2 py-2 pl-3 pr-2 text-sm transition',
                                       'border-auth-600 font-semibold text-auth-700' => $slugPanduan === $slug,
                                       'border-transparent text-slate-600 hover:border-slate-300 hover:text-auth-700' => $slugPanduan !== $slug,
                                   ])>
                                    {{ $item['judul'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </div>
        </aside>

        {{-- Konten utama --}}
        <article class="min-w-0">
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-7 shadow-sm sm:px-9 sm:py-9">
                <header class="mb-7 border-b border-slate-100 pb-6">
                    <a href="{{ route('panduan.index') }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-auth-600 hover:text-auth-700">
                        <x-heroicon-m-arrow-left class="h-3.5 w-3.5" />
                        Semua Panduan
                    </a>
                    <h1 class="mt-3 text-2xl font-bold tracking-tight text-auth-900 sm:text-[1.75rem]">
                        {{ $meta['judul'] }}
                    </h1>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $meta['ringkas'] }}</p>
                </header>

                <div class="docs-content">
                    {!! $konten !!}
                </div>
            </div>

            {{-- Prev / Next --}}
            <nav class="mt-6 grid gap-3 sm:grid-cols-2">
                @if ($prev)
                    <a href="{{ route('panduan.show', $prev) }}"
                       class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-auth-300 hover:shadow-sm">
                        <x-heroicon-m-arrow-left class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-auth-600" />
                        <span class="min-w-0">
                            <span class="block text-[11px] font-medium uppercase tracking-wide text-slate-400">Sebelumnya</span>
                            <span class="block truncate text-sm font-semibold text-slate-700">{{ $panduan[$prev]['judul'] }}</span>
                        </span>
                    </a>
                @else
                    <span class="hidden sm:block"></span>
                @endif

                @if ($next)
                    <a href="{{ route('panduan.show', $next) }}"
                       class="group flex items-center justify-end gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-right transition hover:border-auth-300 hover:shadow-sm">
                        <span class="min-w-0">
                            <span class="block text-[11px] font-medium uppercase tracking-wide text-slate-400">Berikutnya</span>
                            <span class="block truncate text-sm font-semibold text-slate-700">{{ $panduan[$next]['judul'] }}</span>
                        </span>
                        <x-heroicon-m-arrow-right class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-auth-600" />
                    </a>
                @endif
            </nav>
        </article>

        {{-- TOC kanan (desktop besar) --}}
        @if (! empty($toc))
            <aside class="hidden xl:block">
                <div class="sticky top-24">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">
                        Di halaman ini
                    </p>
                    <ul class="docs-toc mt-3 space-y-1.5 border-l border-slate-200 pl-3 text-sm">
                        @foreach ($toc as $item)
                            <li>
                                <a href="#{{ $item['id'] }}" data-toc-link class="block leading-snug transition">
                                    {{ $item['text'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        @endif
    </div>

    @push('scripts')
        <script>
            (() => {
                const links = Array.from(document.querySelectorAll('[data-toc-link]'));
                if (!links.length) return;

                const headings = links
                    .map((link) => document.getElementById(link.getAttribute('href').slice(1)))
                    .filter(Boolean);

                const setActive = (id) => {
                    links.forEach((link) => {
                        link.classList.toggle('is-active', link.getAttribute('href') === '#' + id);
                    });
                };

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) setActive(entry.target.id);
                    });
                }, { rootMargin: '-96px 0px -70% 0px', threshold: 0 });

                headings.forEach((heading) => observer.observe(heading));
            })();
        </script>
    @endpush
</x-landing-auth-layout>
