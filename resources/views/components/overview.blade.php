@props([
    'navItems' => [],
])

@php
    $canUseAi = \Hwkdo\IntranetAppBase\Support\AiUsage::allowed();

    $linkItems = collect($navItems)->filter(function (array $item): bool {
        return ($item['type'] ?? '') !== 'separator' && isset($item['href']);
    });

    $usesWelcomeSection = $linkItems->contains(fn (array $item): bool => array_key_exists('welcomeSection', $item));

    if ($usesWelcomeSection) {
        $mainNavItems = $linkItems
            ->filter(fn (array $item): bool => ($item['welcomeSection'] ?? 'main') === 'main')
            ->values();
        $settingsItems = $linkItems
            ->filter(fn (array $item): bool => ($item['welcomeSection'] ?? '') === 'settings')
            ->values();
    } else {
        $mainNavItems = $linkItems
            ->filter(function (array $item): bool {
                return ! in_array($item['label'], ['Meine Einstellungen', 'Admin', 'Admin-Settings'], true)
                    && ! str_contains($item['href'], '/settings/')
                    && ! str_contains($item['href'], '/admin');
            })
            ->values();
        $settingsItems = $linkItems
            ->filter(function (array $item): bool {
                return in_array($item['label'], ['Meine Einstellungen', 'Admin', 'Admin-Settings'], true)
                    || str_contains($item['href'], '/settings/')
                    || str_contains($item['href'], '/admin');
            })
            ->values();
    }

    $currentUrl = rtrim(url()->current(), '/');

    $visible = function ($items) use ($currentUrl) {
        return collect($items)
            ->filter(function (array $item) use ($currentUrl): bool {
                if (isset($item['permission']) && ! auth()->user()?->can($item['permission'])) {
                    return false;
                }

                return rtrim((string) $item['href'], '/') !== $currentUrl;
            })
            ->values();
    };

    $groups = [
        ['title' => 'Bereiche', 'description' => 'Abwesenheit einrichten und für Ihr Team verwalten.', 'items' => $visible($mainNavItems)],
        ['title' => 'Einstellungen', 'description' => 'Persönliche Vorgaben, Benachrichtigungen und Verwaltung.', 'items' => $visible($settingsItems)],
    ];

    $groups = array_values(array_filter($groups, fn (array $group): bool => $group['items']->isNotEmpty()));
@endphp

@if ($groups === [])
    <x-intranet-app-abwesenheit::empty-state title="Keine Bereiche" icon="squares-2x2">
        Für Ihr Konto sind in dieser App keine Einträge freigeschaltet.
    </x-intranet-app-abwesenheit::empty-state>
@else
    <div class="space-y-4">
        @foreach ($groups as $group)
            <section class="space-y-3">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight text-[#073070] dark:text-white">{{ $group['title'] }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-white/70">{{ $group['description'] }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($group['items'] as $item)
                        <article wire:key="abwesenheit-overview-{{ $item['href'] }}" class="glass-card flex flex-col overflow-hidden">
                            <div class="flex items-center gap-3 border-b border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                                    <flux:icon :icon="$item['icon'] ?? 'document-text'" variant="micro" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-[#073070] dark:text-white">{{ $item['label'] }}</h3>
                                    <p class="text-xs text-slate-600 dark:text-white/70">{{ $item['description'] ?? $item['label'] }}</p>
                                </div>
                            </div>

                            <div class="mt-auto px-5 py-4">
                                @if (! empty($item['requiresAiUsage']) && ! $canUseAi)
                                    <x-intranet-app-base::ai-usage-locked-button :label="$item['buttonText'] ?? $item['label']" />
                                @else
                                    <a
                                        href="{{ $item['href'] }}"
                                        wire:navigate
                                        class="inline-flex w-full items-center justify-center rounded-full bg-[#073070] px-3 py-2 text-sm font-semibold text-white shadow-md shadow-[#073070]/25 transition hover:bg-[#04214e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]"
                                    >{{ $item['buttonText'] ?? $item['label'] }}</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
