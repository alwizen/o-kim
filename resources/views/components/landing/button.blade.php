@props(['variant' => 'primary', 'size' => 'md'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-300 focus-visible:outline-none';

    $sizes = [
        'sm' => 'px-4 py-2 text-sm',
        'md' => 'px-6 py-3 text-sm',
        'lg' => 'px-7 py-3.5 text-base',
    ];

    $variants = [
        'primary' => 'bg-ocean-600 text-white shadow-lg shadow-ocean-900/20 hover:bg-ocean-700 hover:shadow-xl hover:shadow-ocean-900/25 hover:-translate-y-0.5',
        'secondary' => 'bg-white/80 text-ocean-800 border border-ocean-200 backdrop-blur hover:border-ocean-400 hover:bg-white hover:-translate-y-0.5',
        'ghost' => 'text-ocean-700 hover:bg-ocean-50',
        'light' => 'bg-white text-ocean-800 shadow-lg shadow-ocean-900/10 hover:bg-ocean-50 hover:-translate-y-0.5',
        'outline-light' => 'border border-white/40 text-white hover:bg-white/10 hover:border-white/60 hover:-translate-y-0.5',
    ];
@endphp

<a
    {{ $attributes->merge([
        'class' => trim($base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary'])),
    ]) }}
>
    {{ $slot }}
</a>
