@props([
    'icon',
    'title',
    'description' => null,
])

<div {{ $attributes->class('flex items-center gap-3 border-b border-[#d0e3f9] px-5 py-4 dark:border-white/10') }}>
    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
        <flux:icon :icon="$icon" variant="micro" class="size-5" />
    </span>
    <div class="min-w-0">
        <h2 class="text-sm font-semibold text-[#073070] dark:text-white">{{ $title }}</h2>
        @if (filled($description))
            <p class="text-xs text-slate-600 dark:text-white/70">{{ $description }}</p>
        @endif
    </div>
</div>
