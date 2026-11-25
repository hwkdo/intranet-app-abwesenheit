<?php

use Flux\Flux;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Events\AbwesenheitStatusChanged;
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
     *     mailbox: array{delegated: bool, delegate_names: string},
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

        $result = app(AbwesenheitService::class)->destroy($user);
        app(AbwesenheitScheduleProcessor::class)->markEndedEarlyForUser((int) $user->id);

        Cache::forget($this->statusCacheKey((int) $user->id));
        $this->forgetComputedStatus();
        AbwesenheitStatusChanged::dispatch((int) $user->id);

        if ($result->warnings !== []) {
            Flux::toast(text: implode(' ', $result->warnings), variant: 'warning');
        }

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
        AbwesenheitStatusChanged::dispatch((int) $user->id);

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

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $user = auth()->user();

        if ($user === null || ! $user->can('see-app-abwesenheit')) {
            return [];
        }

        return [
            'echo-private:App.Models.User.'.$user->id.',.abwesenheit.status-changed' => 'refreshFromBroadcast',
        ];
    }

    public function refreshFromBroadcast(): void
    {
        $userId = auth()->id();

        if ($userId !== null) {
            Cache::forget($this->statusCacheKey((int) $userId));
        }

        $this->forgetComputedStatus();
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
     * @param  array{outlook?: mixed, phone?: mixed, d3?: mixed, mailbox?: mixed, fetch_errors?: mixed}  $raw
     * @return array{
     *     outlook: array{status: string, scheduledEndDateTime: mixed},
     *     phone: string,
     *     d3: array{abwesend: bool, vertreter: array<string, string>|null, vertreter_name: string},
     *     mailbox: array{delegated: bool, delegate_names: string},
     *     fetch_errors: array{outlook: bool, phone: bool, d3: bool}
     * }
     */
    private function normalizeStatus(array $raw): array
    {
        $d3 = is_array($raw['d3'] ?? null) ? $raw['d3'] : [];
        $mailbox = is_array($raw['mailbox'] ?? null) ? $raw['mailbox'] : [];
        $errors = is_array($raw['fetch_errors'] ?? null) ? $raw['fetch_errors'] : [];
        $delegates = is_array($mailbox['delegates'] ?? null) ? $mailbox['delegates'] : [];

        return [
            'outlook' => $this->normalizeOutlook($raw['outlook'] ?? null),
            'phone' => (string) ($raw['phone'] ?? ''),
            'd3' => [
                'abwesend' => (bool) ($d3['abwesend'] ?? false),
                'vertreter' => is_array($d3['vertreter'] ?? null) ? $d3['vertreter'] : null,
                'vertreter_name' => $this->formatD3VertreterName($d3['vertreter'] ?? null),
            ],
            'mailbox' => [
                'delegated' => (bool) ($mailbox['delegated'] ?? false),
                'delegate_names' => collect($delegates)
                    ->pluck('name')
                    ->filter()
                    ->implode(', '),
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
    <div class="glass-card h-full space-y-3 p-5">
        <flux:skeleton class="h-4 w-52" />
        <flux:skeleton class="h-3 w-64" />
        <flux:skeleton class="h-10 w-full rounded-xl" />
        <flux:skeleton class="h-10 w-full rounded-xl" />
        <flux:skeleton class="h-10 w-full rounded-xl" />
        <flux:skeleton class="h-10 w-full rounded-xl" />
    </div>
@endplaceholder

<x-intranet-app-base::dashboard.widget-card
    title="Mein Abwesenheitsstatus"
    description="Outlook, Telefon, d3 und Postfach"
>
    <div class="flex items-center justify-between gap-3">
        @if ($this->overallState === 'absent')
            <x-intranet-app-abwesenheit::status-pill active>Abwesend</x-intranet-app-abwesenheit::status-pill>
        @elseif ($this->overallState === 'planned')
            <x-intranet-app-abwesenheit::status-pill active>Geplant</x-intranet-app-abwesenheit::status-pill>
        @else
            <x-intranet-app-abwesenheit::status-pill>Anwesend</x-intranet-app-abwesenheit::status-pill>
        @endif
    </div>

    <div class="divide-y divide-[#d0e3f9] dark:divide-white/10">
        <div class="py-2.5">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-medium text-[#073070] dark:text-white">Outlook</p>
                @if ($this->status['fetch_errors']['outlook'])
                    <x-intranet-app-abwesenheit::status-pill active>Fehler</x-intranet-app-abwesenheit::status-pill>
                @elseif ($this->outlookStatus() === 'alwaysEnabled' || $this->outlookStatus() === 'scheduled')
                    <x-intranet-app-abwesenheit::status-pill active>Aktiv</x-intranet-app-abwesenheit::status-pill>
                @else
                    <x-intranet-app-abwesenheit::status-pill>Aus</x-intranet-app-abwesenheit::status-pill>
                @endif
            </div>
            @if ($this->status['fetch_errors']['outlook'])
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Outlook-Status konnte nicht abgerufen werden.</p>
            @elseif ($this->outlookStatus() === 'scheduled' && $this->outlookEndLabel())
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Bis {{ $this->outlookEndLabel() }}</p>
            @elseif ($this->outlookStatus() === 'alwaysEnabled')
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Ohne Enddatum</p>
            @endif
        </div>

        <div class="py-2.5">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-medium text-[#073070] dark:text-white">Telefon</p>
                @if ($this->status['fetch_errors']['phone'])
                    <x-intranet-app-abwesenheit::status-pill active>Fehler</x-intranet-app-abwesenheit::status-pill>
                @elseif (strlen($this->status['phone']) > 0)
                    <x-intranet-app-abwesenheit::status-pill active>Aktiv</x-intranet-app-abwesenheit::status-pill>
                @else
                    <x-intranet-app-abwesenheit::status-pill>Aus</x-intranet-app-abwesenheit::status-pill>
                @endif
            </div>
            @if ($this->status['fetch_errors']['phone'])
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Telefonstatus konnte nicht abgerufen werden.</p>
            @elseif (strlen($this->status['phone']) > 0)
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Umgeleitet auf {{ $this->status['phone'] }}</p>
            @endif
        </div>

        <div class="py-2.5">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-medium text-[#073070] dark:text-white">d3</p>
                @if ($this->status['fetch_errors']['d3'])
                    <x-intranet-app-abwesenheit::status-pill active>Fehler</x-intranet-app-abwesenheit::status-pill>
                @elseif ($this->status['d3']['abwesend'])
                    <x-intranet-app-abwesenheit::status-pill active>Aktiv</x-intranet-app-abwesenheit::status-pill>
                @else
                    <x-intranet-app-abwesenheit::status-pill>Aus</x-intranet-app-abwesenheit::status-pill>
                @endif
            </div>
            @if ($this->status['fetch_errors']['d3'])
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">d3-Status konnte nicht abgerufen werden.</p>
            @elseif ($this->status['d3']['abwesend'] && $this->d3VertreterName() !== '')
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Vertretung: {{ $this->d3VertreterName() }}</p>
            @elseif ($this->status['d3']['abwesend'])
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Kein Vertreter hinterlegt</p>
            @endif
        </div>

        <div class="py-2.5">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-medium text-[#073070] dark:text-white">Postfach</p>
                @if ($this->status['mailbox']['delegated'])
                    <x-intranet-app-abwesenheit::status-pill active>Aktiv</x-intranet-app-abwesenheit::status-pill>
                @else
                    <x-intranet-app-abwesenheit::status-pill>Aus</x-intranet-app-abwesenheit::status-pill>
                @endif
            </div>
            @if ($this->status['mailbox']['delegated'] && $this->status['mailbox']['delegate_names'] !== '')
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Delegiert an {{ $this->status['mailbox']['delegate_names'] }}</p>
            @elseif ($this->status['mailbox']['delegated'])
                <p class="mt-1 text-xs text-slate-600 dark:text-white/70">Delegierung aktiv</p>
            @endif
        </div>
    </div>

    @if ($this->pendingSchedules->isNotEmpty())
        <div class="divide-y divide-[#d0e3f9] dark:divide-white/10">
            @foreach ($this->pendingSchedules as $schedule)
                <div
                    wire:key="abwesenheit-pending-schedule-{{ $schedule->id }}"
                    class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                >
                    <div>
                        <p class="text-sm font-medium text-[#073070] dark:text-white">Geplant</p>
                        <p class="text-xs text-slate-600 dark:text-white/70">
                            {{ $schedule->starts_at->format('d.m.Y') }} – {{ $schedule->ends_at->format('d.m.Y') }}
                        </p>
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
        @if ($this->isAbsent)
            <flux:button
                size="sm"
                variant="danger"
                wire:click="deactivate"
                wire:loading.attr="disabled"
                wire:target="deactivate"
            >
                <span wire:loading.remove wire:target="deactivate">Wieder anwesend</span>
                <span wire:loading wire:target="deactivate">Wird deaktiviert…</span>
            </flux:button>
        @endif

        <flux:button variant="ghost" size="sm" :href="route('apps.abwesenheit.meine')" wire:navigate>
            Zur App
        </flux:button>
    </div>
</x-intranet-app-base::dashboard.widget-card>
