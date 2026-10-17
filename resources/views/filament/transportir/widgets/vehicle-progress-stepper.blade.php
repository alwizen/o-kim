@php
    $stateColor = [
        'done' => 'var(--success-600, #16a34a)',
        'active' => 'var(--primary-600, #2563eb)',
        'warning' => 'var(--warning-600, #d97706)',
        'failed' => 'var(--danger-600, #dc2626)',
        'pending' => 'var(--gray-300, #d1d5db)',
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Progres Pengajuan KIM</x-slot>
        <x-slot name="description">
            Tahapan pengajuan mulai dari kendaraan terdaftar hingga KIM terbit.
        </x-slot>

        @if (empty($options))
            <div class="vps-empty">
                <p class="vps-empty-title">Belum ada pengajuan KIM</p>
                <p class="vps-empty-text">
                    Buat pengajuan KIM terlebih dahulu agar progres tiap tahap dapat dipantau di sini.
                </p>
                <x-filament::button tag="a" href="/transportir/kim-applications/create" icon="heroicon-o-plus">
                    Buat Pengajuan KIM
                </x-filament::button>
            </div>
        @else
            <div class="vps-toolbar">
                <label for="vps-application" class="vps-label">Kendaraan / Pengajuan</label>
                <select id="vps-application" wire:model.live="applicationId" class="vps-select">
                    @foreach ($options as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if (! empty($steps))
                <ol class="vps-stepper">
                    @foreach ($steps as $index => $step)
                        <li @class([
                            'vps-step',
                            'vps-step--'.$step['state'],
                            'vps-step--current' => $index === $currentIndex,
                        ])>
                            <a href="{{ $step['url'] }}" class="vps-step-link">
                                <span class="vps-marker" style="--vps-color: {{ $stateColor[$step['state']] ?? $stateColor['pending'] }}">
                                    @if ($step['state'] === 'done')
                                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.704 5.296a1 1 0 0 1 0 1.415l-7.5 7.5a1 1 0 0 1-1.415 0l-3.5-3.5a1 1 0 0 1 1.415-1.415l2.792 2.793 6.793-6.793a1 1 0 0 1 1.415 0Z" clip-rule="evenodd"/>
                                        </svg>
                                    @elseif ($step['state'] === 'failed')
                                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm2.53-10.53a.75.75 0 0 0-1.06-1.06L10 7.94 8.53 6.47a.75.75 0 0 0-1.06 1.06L8.94 9l-1.47 1.47a.75.75 0 1 0 1.06 1.06L10 10.06l1.47 1.47a.75.75 0 1 0 1.06-1.06L11.06 9l1.47-1.47Z" clip-rule="evenodd"/>
                                        </svg>
                                    @elseif ($step['state'] === 'warning')
                                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                                        </svg>
                                    @elseif ($step['state'] === 'active')
                                        <span class="vps-dot"></span>
                                    @else
                                        <span class="vps-index">{{ $index + 1 }}</span>
                                    @endif
                                </span>

                                <span class="vps-text">
                                    <span class="vps-title">{{ $step['label'] }}</span>
                                    <span class="vps-desc">{{ $step['description'] }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            @endif
        @endif
    </x-filament::section>

    <style>
        .vps-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-bottom: 1.5rem; }
        .vps-label { font-size: .875rem; font-weight: 600; color: var(--gray-700, #374151); }
        .vps-select {
            min-width: 16rem; max-width: 100%; padding: .5rem .75rem; font-size: .875rem;
            color: var(--gray-950, #111827); background-color: var(--gray-50, #f9fafb);
            border: 1px solid var(--gray-300, #d1d5db); border-radius: .5rem;
        }
        .vps-select:focus { outline: 2px solid var(--primary-600, #2563eb); outline-offset: 1px; }

        .vps-stepper {
            display: grid; grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: .75rem; list-style: none; margin: 0; padding: 0;
        }
        .vps-step { position: relative; }
        .vps-step:not(:last-child)::after {
            content: ''; position: absolute; top: 1.125rem; left: calc(50% + 1.5rem); right: calc(-50% + 1.5rem);
            height: 2px; background-color: var(--gray-200, #e5e7eb); border-radius: 9999px;
        }
        .vps-step--done:not(:last-child)::after { background-color: var(--success-500, #22c55e); }

        .vps-step-link {
            display: flex; flex-direction: column; align-items: center; gap: .625rem;
            text-align: center; text-decoration: none; height: 100%;
        }

        .vps-marker {
            position: relative; z-index: 1;
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.25rem; height: 2.25rem; border-radius: 9999px;
            background-color: var(--vps-color);
            color: #fff; flex-shrink: 0;
        }
        .vps-marker svg { width: 1.25rem; height: 1.25rem; }
        .vps-step--pending .vps-marker {
            background-color: var(--gray-100, #f3f4f6);
            color: var(--gray-500, #6b7280);
            box-shadow: inset 0 0 0 2px var(--gray-300, #d1d5db);
        }
        .vps-step--current .vps-marker { box-shadow: 0 0 0 4px color-mix(in oklab, var(--vps-color) 25%, transparent); }

        .vps-dot { width: .5rem; height: .5rem; border-radius: 9999px; background-color: #fff; animation: vps-pulse 1.5s ease-in-out infinite; }
        .vps-index { font-size: .75rem; font-weight: 700; }
        @keyframes vps-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }

        .vps-text { display: flex; flex-direction: column; gap: .125rem; }
        .vps-title { font-size: .8125rem; font-weight: 600; color: var(--gray-950, #111827); line-height: 1.25; }
        .vps-desc { font-size: .75rem; color: var(--gray-500, #6b7280); line-height: 1.35; }
        .vps-step--pending .vps-title { color: var(--gray-500, #6b7280); }
        .vps-step-link:hover .vps-title { text-decoration: underline; }

        .vps-empty { display: flex; flex-direction: column; align-items: center; gap: .5rem; text-align: center; padding: 2rem 1rem; }
        .vps-empty-title { font-size: .9375rem; font-weight: 600; color: var(--gray-950, #111827); margin: 0; }
        .vps-empty-text { font-size: .875rem; color: var(--gray-500, #6b7280); margin: 0 0 .75rem; max-width: 28rem; }

        @media (max-width: 768px) {
            .vps-stepper { grid-template-columns: 1fr; gap: 1rem; }
            .vps-step:not(:last-child)::after {
                top: 2.5rem; bottom: -.75rem; left: 1.125rem; right: auto; width: 2px; height: auto;
            }
            .vps-step-link { flex-direction: row; align-items: flex-start; text-align: left; gap: .875rem; }
            .vps-text { padding-top: .25rem; }
        }
    </style>
</x-filament-widgets::widget>
