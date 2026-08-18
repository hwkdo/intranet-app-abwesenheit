<?php

use Flux\Flux;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitScheduleProcessor;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        $this->authorize('see-app-abwesenheit');
    }

    /**
     * @return array{
     *     outlook: array{status: string, scheduledEndDateTime: mixed},
     *     phone: string,
     *     d3: array{abwesend: bool, vertreter: array<string, string>|null, vertreter_name: string},
     *     fetch_errors: array{outlook: bool, phone: bool, d3: bool}
     * }
     */
    #[Computed]
    public function status(): array
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $ttl = (int) config('intranet-app-abwesenheit.widget_status_cache_seconds', 120);

        return Cache::remember(
            $this->statusCacheKey((int) $user->id),
            $ttl,
            fn (): array => $this->normalizeStatus(app(AbwesenheitService::class)->show($user)),
        );
    }

    #[Computed]
    public function isAbsent(): bool
    {
        return app(AbwesenheitService::class)->isActiveFromShow($this->status);
    }

    /**
     * @return Collection<int, AbwesenheitSchedule>
     */
    #[Computed]
    public function pendingSchedules(): Collection
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        return AbwesenheitSchedule::query()
            ->where('user_id', $user->id)
            ->where('status', AbwesenheitScheduleStatus::Pending)
            ->orderBy('starts_at')
            ->get();
    }

    #[Computed]
    public function overallState(): string
    {
        if ($this->isAbsent) {
            return 'absent';
        }

        if ($this->pendingSchedules->isNotEmpty()) {
            return 'planned';
        }

        return 'present';
    }

    public function deactivate(): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        Gate::authorize('abwesenheit.manage', $user);

        app(AbwesenheitService::class)->destroy($user);
        app(AbwesenheitScheduleProcessor::class)->markEndedEarlyForUser((int) $user->id);

        Cache::forget($this->statusCacheKey((int) $user->id));
        $this->forgetComputedStatus();

        Flux::toast(text: 'Abwesenheit wurde deaktiviert.', variant: 'success');
    }

    public function cancelSchedule(int $scheduleId): void
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        Gate::authorize('abwesenheit.manage', $user);

        $schedule = AbwesenheitSchedule::query()
            ->where('user_id', $user->id)
            ->where('status', AbwesenheitScheduleStatus::Pending)
            ->findOrFail($scheduleId);

        $schedule->update(['status' => AbwesenheitScheduleStatus::Cancelled]);

        unset($this->pendingSchedules);
        unset($this->overallState);

        Flux::toast(text: 'Geplante Abwesenheit wurde storniert.', variant: 'success');
    }

    public function outlookStatus(): string
    {
        return $this->status['outlook']['status'] ?? 'disabled';
    }

    public function outlookEndLabel(): ?string
    {
        $raw = $this->status['outlook']['scheduledEndDateTime']['dateTime'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('d.m.Y');
        } catch (\Throwable) {
            return $raw;
        }
    }

    public function d3VertreterName(): string
    {
        return $this->status['d3']['vertreter_name'] ?? '';
    }

    private function statusCacheKey(int $userId): string
    {
        return 'intranet-app-abwesenheit.widget-status.'.$userId;
    }

    private function forgetComputedStatus(): void
    {
        unset($this->status);
        unset($this->isAbsent);
        unset($this->pendingSchedules);
        unset($this->overallState);
    }

    /**
     * @param  array{outlook?: mixed, phone?: mixed, d3?: mixed, fetch_errors?: mixed}  $raw
     * @return array{
     *     outlook: array{status: string, scheduledEndDateTime: mixed},
     *     phone: string,
     *     d3: array{abwesend: bool, vertreter: array<string, string>|null, vertreter_name: string},
     *     fetch_errors: array{outlook: bool, phone: bool, d3: bool}
     * }
     */
    private function normalizeStatus(array $raw): array
    {
        $d3 = is_array($raw['d3'] ?? null) ? $raw['d3'] : [];
        $errors = is_array($raw['fetch_errors'] ?? null) ? $raw['fetch_errors'] : [];

        return [
            'outlook' => $this->normalizeOutlook($raw['outlook'] ?? null),
            'phone' => (string) ($raw['phone'] ?? ''),
            'd3' => [
                'abwesend' => (bool) ($d3['abwesend'] ?? false),
                'vertreter' => is_array($d3['vertreter'] ?? null) ? $d3['vertreter'] : null,
                'vertreter_name' => $this->formatD3VertreterName($d3['vertreter'] ?? null),
            ],
            'fetch_errors' => [
                'outlook' => (bool) ($errors['outlook'] ?? false),
                'phone' => (bool) ($errors['phone'] ?? false),
                'd3' => (bool) ($errors['d3'] ?? false),
            ],
        ];
    }

    /**
     * @return array{status: string, scheduledEndDateTime: mixed}
     */
    private function normalizeOutlook(mixed $outlook): array
    {
        if (is_array($outlook)) {
            return [
                'status' => (string) ($outlook['status'] ?? 'disabled'),
                'scheduledEndDateTime' => $outlook['scheduledEndDateTime'] ?? null,
            ];
        }

        $status = 'disabled';
        if (is_object($outlook) && method_exists($outlook, 'getStatus')) {
            $rawStatus = $outlook->getStatus();
            $status = method_exists($rawStatus, 'value')
                ? (string) $rawStatus->value()
                : (string) $rawStatus;
        }

        return [
            'status' => $status,
            'scheduledEndDateTime' => null,
        ];
    }

    private function formatD3VertreterName(mixed $vertreter): string
    {
        if (! is_array($vertreter)) {
            return '';
        }

        $name = trim(($vertreter['vorname'] ?? '').' '.($vertreter['nachname'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim((string) ($vertreter['name'] ?? ''));
    }
};
?>

@placeholder
    <flux:card class="h-full">
        <div class="mb-3 space-y-2">
            <flux:skeleton class="h-4 w-52" />
            <flux:skeleton class="h-3 w-64" />
        </div>
        <div class="space-y-2">
            <flux:skeleton class="h-10 w-full rounded-md" />
            <flux:skeleton class="h-10 w-full rounded-md" />
            <flux:skeleton class="h-10 w-full rounded-md" />
        </div>
    </flux:card>
@endplaceholder

<x-intranet-app-base::dashboard.widget-card
    title="Mein Abwesenheitsstatus"
    description="Outlook, Telefon und d3"
>
    <div class="flex items-center justify-between gap-3">
        @if($this->overallState === 'absent')
            <flux:badge color="amber" size="sm">Abwesend</flux:badge>
        @elseif($this->overallState === 'planned')
            <flux:badge color="blue" size="sm">Geplant</flux:badge>
        @else
            <flux:badge color="green" size="sm">Anwesend</flux:badge>
        @endif
    </div>

    <div class="space-y-2">
        <div class="rounded-md border border-zinc-200 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800/40">
            <div class="flex items-start justify-between gap-3">
                <div class="font-medium">Outlook</div>
                @if($this->status['fetch_errors']['outlook'])
                    <flux:badge color="red" size="sm">Fehler</flux:badge>
                @elseif($this->outlookStatus() === 'alwaysEnabled' || $this->outlookStatus() === 'scheduled')
                    <flux:badge color="amber" size="sm">Aktiv</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Aus</flux:badge>
                @endif
            </div>
            @if($this->status['fetch_errors']['outlook'])
                <div class="mt-1 text-xs text-red-700 dark:text-red-300">Outlook-Status konnte nicht abgerufen werden.</div>
            @elseif($this->outlookStatus() === 'scheduled' && $this->outlookEndLabel())
                <div class="mt-1 text-xs text-zinc-500 dark:text-white/70">Bis {{ $this->outlookEndLabel() }}</div>
            @elseif($this->outlookStatus() === 'alwaysEnabled')
                <div class="mt-1 text-xs text-zinc-500 dark:text-white/70">Ohne Enddatum</div>
            @endif
        </div>

        <div class="rounded-md border border-zinc-200 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800/40">
            <div class="flex items-start justify-between gap-3">
                <div class="font-medium">Telefon</div>
                @if($this->status['fetch_errors']['phone'])
                    <flux:badge color="red" size="sm">Fehler</flux:badge>
                @elseif(strlen($this->status['phone']) > 0)
                    <flux:badge color="amber" size="sm">Aktiv</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Aus</flux:badge>
                @endif
            </div>
            @if($this->status['fetch_errors']['phone'])
                <div class="mt-1 text-xs text-red-700 dark:text-red-300">Telefonstatus konnte nicht abgerufen werden.</div>
            @elseif(strlen($this->status['phone']) > 0)
                <div class="mt-1 text-xs text-zinc-500 dark:text-white/70">Umgeleitet auf {{ $this->status['phone'] }}</div>
            @endif
        </div>

        <div class="rounded-md border border-zinc-200 px-3 py-2 dark:border-zinc-600 dark:bg-zinc-800/40">
            <div class="flex items-start justify-between gap-3">
                <div class="font-medium">d3</div>
                @if($this->status['fetch_errors']['d3'])
                    <flux:badge color="red" size="sm">Fehler</flux:badge>
                @elseif($this->status['d3']['abwesend'])
                    <flux:badge color="amber" size="sm">Aktiv</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Aus</flux:badge>
                @endif
            </div>
            @if($this->status['fetch_errors']['d3'])
                <div class="mt-1 text-xs text-red-700 dark:text-red-300">d3-Status konnte nicht abgerufen werden.</div>
            @elseif($this->status['d3']['abwesend'] && $this->d3VertreterName() !== '')
                <div class="mt-1 text-xs text-zinc-500 dark:text-white/70">Vertretung: {{ $this->d3VertreterName() }}</div>
            @elseif($this->status['d3']['abwesend'])
                <div class="mt-1 text-xs text-zinc-500 dark:text-white/70">Kein Vertreter hinterlegt</div>
            @endif
        </div>
    </div>

    @if($this->pendingSchedules->isNotEmpty())
        <div class="space-y-2">
            @foreach($this->pendingSchedules as $schedule)
                <div
                    wire:key="abwesenheit-pending-schedule-{{ $schedule->id }}"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-zinc-200 px-3 py-2 dark:border-zinc-600"
                >
                    <div>
                        <div class="text-sm font-medium">Geplant</div>
                        <div class="text-xs text-zinc-500 dark:text-white/70">
                            {{ $schedule->starts_at->format('d.m.Y') }} – {{ $schedule->ends_at->format('d.m.Y') }}
                        </div>
                    </div>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="cancelSchedule({{ $schedule->id }})"
                        wire:loading.attr="disabled"
                        wire:target="cancelSchedule({{ $schedule->id }})"
                    >
                        Stornieren
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-2 pt-1">
        @if($this->isAbsent)
            <flux:button
                size="sm"
                variant="danger"
                wire:click="deactivate"
                wire:loading.attr="disabled"
                wire:target="deactivate"
            >
                <span wire:loading.remove wire:target="deactivate">Wieder anwesend</span>
                <span wire:loading wire:target="deactivate">Deaktiviere…</span>
            </flux:button>
        @endif

        <flux:button variant="ghost" size="sm" :href="route('apps.abwesenheit.meine')" wire:navigate>
            Zur App
        </flux:button>
    </div>
</x-intranet-app-base::dashboard.widget-card>
