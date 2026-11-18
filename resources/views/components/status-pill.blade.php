@props([
    'active' => false,
])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
    'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $active,
    'bg-white/80 text-[#073070] ring-1 ring-[#d0e3f9] dark:bg-white/10 dark:text-white dark:ring-white/15' => ! $active,
]) }}>{{ $slot }}</span>
