@props(['eyebrow' => null, 'title', 'description' => null, 'align' => 'center'])

@php
    $alignClass = $align === 'left' ? 'text-left mx-0' : 'text-center mx-auto';
@endphp

<div class="reveal max-w-2xl {{ $alignClass }}">
    @if ($eyebrow)
        <span class="inline-flex items-center gap-2 rounded-full border border-navy-100 bg-navy-50/70 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-navy-600">
            <span class="h-1.5 w-1.5 rounded-full bg-navy-500"></span>
            {{ $eyebrow }}
        </span>
    @endif

    <h2 class="text-balance-heading mt-5 text-3xl font-bold tracking-tight text-navy-900 sm:text-4xl">
        {{ $title }}
    </h2>

    @if ($description)
        <p class="mt-4 text-base leading-relaxed text-slate-600 sm:text-lg">
            {{ $description }}
        </p>
    @endif
</div>
