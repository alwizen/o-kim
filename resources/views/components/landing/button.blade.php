@props(['variant' => 'primary', 'size' => 'md'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-300 focus-visible:outline-none';

    $sizes = [
        'sm' => 'px-4 py-2 text-sm',
        'md' => 'px-6 py-3 text-sm',
        'lg' => 'px-7 py-3.5 text-base',
    ];

    $variants = [
        'primary' => 'bg-navy-700 text-white shadow-lg shadow-navy-900/15 hover:bg-navy-800 hover:shadow-xl hover:shadow-navy-900/20 hover:-translate-y-0.5',
        'secondary' => 'bg-white text-navy-800 border border-navy-200 hover:border-navy-400 hover:bg-navy-50 hover:-translate-y-0.5',
        'ghost' => 'text-navy-700 hover:bg-navy-50',
        'light' => 'bg-white text-navy-800 hover:bg-navy-50 shadow-lg shadow-navy-950/20 hover:-translate-y-0.5',
        'outline-light' => 'border border-white/40 text-white hover:bg-white/10 hover:border-white/70',
    ];
@endphp

<a
    {{ $attributes->merge([
        'class' => trim($base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary'])),
    ]) }}
>
    {{ $slot }}
</a>
