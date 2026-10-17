@props(['variant' => 'primary', 'size' => 'md'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-300 focus-visible:outline-none';

    $sizes = [
        'sm' => 'px-4 py-2 text-sm',
        'md' => 'px-6 py-3 text-sm',
        'lg' => 'px-7 py-3.5 text-base',
    ];

    $variants = [
        'primary' => 'bg-lavender-600 text-white shadow-lg shadow-lavender-900/20 hover:bg-lavender-700 hover:shadow-xl hover:shadow-lavender-900/25 hover:-translate-y-0.5',
        'secondary' => 'bg-white/80 text-lavender-800 border border-lavender-200 backdrop-blur hover:border-lavender-400 hover:bg-white hover:-translate-y-0.5',
        'ghost' => 'text-lavender-700 hover:bg-lavender-50',
        'light' => 'bg-white text-lavender-800 shadow-lg shadow-lavender-900/10 hover:bg-lavender-50 hover:-translate-y-0.5',
        'outline-light' => 'border border-lavender-300 text-lavender-800 hover:bg-white/60 hover:border-lavender-400',
    ];
@endphp

<a
    {{ $attributes->merge([
        'class' => trim($base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary'])),
    ]) }}
>
    {{ $slot }}
</a>
