@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
])

@php
    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.abwesenheit.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen'],
        ['label' => 'Meine Abwesenheit', 'href' => route('apps.abwesenheit.meine'), 'icon' => 'calendar-days', 'description' => 'Abwesenheit einrichten oder verwalten', 'buttonText' => 'Abwesenheit öffnen'],
    ];

    if (auth()->user()?->ist_vorgesetzter) {
        $defaultNavItems[] = ['label' => 'Meine Mitarbeiter', 'href' => route('apps.abwesenheit.mitarbeiter'), 'icon' => 'users', 'description' => 'Abwesenheit für Mitarbeiter verwalten', 'buttonText' => 'Mitarbeiter öffnen'];
    }

    $defaultNavItems = array_merge($defaultNavItems, [
        ['label' => 'Meine Einstellungen', 'href' => route('apps.abwesenheit.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Postfach-Delegierung bei Abwesenheit', 'buttonText' => 'Einstellungen öffnen'],
        ['label' => 'Benachrichtigungen', 'href' => route('apps.abwesenheit.settings.notifications'), 'icon' => 'bell', 'description' => 'Benachrichtigungen dieser App konfigurieren', 'buttonText' => 'Benachrichtigungen öffnen'],
        ['label' => 'App-Info', 'href' => route('apps.abwesenheit.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
        ['label' => 'Admin', 'href' => route('apps.abwesenheit.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-abwesenheit'],
    ]);

    $navItems = ! empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('abwesenheit');
    $canUseAi = \Hwkdo\IntranetAppBase\Support\AiUsage::allowed();
    $currentPath = trim(request()->path(), '/');
    $isIndex = request()->routeIs('apps.abwesenheit.index');
@endphp

@if ($customBgUrl)
    @push('app-styles')
        <style data-app-bg data-ts="{{ uniqid() }}">
            :root { --app-bg-image: url('{{ $customBgUrl }}'); }
        </style>
    @endpush
@endif

<div class="hwk-abwesenheit space-y-4">
    <style>
        .hwk-abwesenheit {
            --color-accent: #073070;
            --color-accent-content: #073070;
            --color-accent-foreground: #ffffff;
        }

        .dark .hwk-abwesenheit {
            --color-accent: #d0e3f9;
            --color-accent-content: #d0e3f9;
            --color-accent-foreground: #04214e;
        }

        .hwk-abwesenheit ui-tabs.hwk-pills {
            height: auto !important;
            flex-wrap: wrap;
            gap: 0.375rem;
            border-bottom-width: 0 !important;
        }

        .hwk-abwesenheit ui-tabs.hwk-pills [data-flux-tab] {
            height: auto;
            margin-bottom: 0 !important;
            border-bottom-width: 0 !important;
            border-radius: 9999px;
            padding: 0.375rem 0.75rem;
            background: rgb(255 255 255 / 0.8) !important;
            color: #073070 !important;
            box-shadow: inset 0 0 0 1px #d0e3f9;
        }

        .dark .hwk-abwesenheit ui-tabs.hwk-pills [data-flux-tab] {
            background: rgb(255 255 255 / 0.1) !important;
            color: #fff !important;
            box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.15);
        }

        .hwk-abwesenheit ui-tabs.hwk-pills [data-flux-tab][data-selected],
        .dark .hwk-abwesenheit ui-tabs.hwk-pills [data-flux-tab][data-selected] {
            background: #073070 !important;
            color: #fff !important;
            box-shadow: 0 4px 6px rgb(7 48 112 / 0.25);
        }
    </style>

    <x-intranet-app-abwesenheit::hero :title="$heading" :description="$subheading" />

    <div class="flex items-start gap-6 max-lg:flex-col">
        <nav
            aria-label="Abwesenheit"
            class="glass-card w-full shrink-0 p-2 lg:sticky lg:top-4 lg:w-64"
            style="--glass-opacity: 0.92"
        >
            <ul class="space-y-0.5">
                @foreach ($navItems as $navItem)
                    @if (($navItem['type'] ?? '') === 'separator')
                        @if (! isset($navItem['permission']) || auth()->user()?->can($navItem['permission']))
                            <li class="px-3 pt-3 pb-1 text-xs font-semibold tracking-[0.2em] text-[#073070]/70 uppercase dark:text-white/60">{{ $navItem['label'] }}</li>
                        @endif
                    @elseif (! isset($navItem['permission']) || auth()->user()?->can($navItem['permission']))
                        <li>
                            @if (! empty($navItem['requiresAiUsage']) && ! $canUseAi)
                                <x-intranet-app-base::ai-usage-locked-nav-item :label="$navItem['label']" />
                            @else
                                @php
                                    $itemPath = trim((string) parse_url((string) ($navItem['href'] ?? ''), PHP_URL_PATH), '/');
                                    $current = $itemPath !== '' && (
                                        $currentPath === $itemPath
                                        || ($itemPath !== 'apps/abwesenheit' && str_starts_with($currentPath, $itemPath.'/'))
                                    );
                                @endphp
                                <a
                                    href="{{ $navItem['href'] }}"
                                    wire:navigate
                                    @class([
                                        'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]',
                                        'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $current,
                                        'text-[#073070] hover:bg-[#d0e3f9]/80 dark:text-white dark:hover:bg-white/10' => ! $current,
                                    ])
                                    @if ($current) aria-current="page" @endif
                                >
                                    @if (! empty($navItem['icon']))
                                        <flux:icon :icon="$navItem['icon']" variant="micro" class="size-4 shrink-0" />
                                    @endif
                                    <span class="truncate">{{ $navItem['label'] }}</span>
                                    @if (isset($navItem['badge']) && $navItem['badge'] !== null && $navItem['badge'] !== '')
                                        <span @class([
                                            'ml-auto inline-flex min-w-5 justify-center rounded-full px-1.5 text-xs font-semibold',
                                            'bg-white/20 text-white' => $current,
                                            'bg-[#073070] text-white' => ! $current,
                                        ])>{{ $navItem['badge'] }}</span>
                                    @endif
                                </a>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 flex-1 space-y-4">
            @if ($isIndex)
                <x-intranet-app-abwesenheit::overview :nav-items="$navItems" />
            @else
                {{ $slot }}
            @endif
        </div>
    </div>
</div>

@push('nav-items')
    @json($navItems)
@endpush
