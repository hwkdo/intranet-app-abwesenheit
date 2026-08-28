<?php

declare(strict_types=1);

use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitStoreData;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitModels;
use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitUserPreferences;
use Flux\Flux;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitScheduleProcessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;
use function Livewire\Volt\title;

function syncAbwesenheitVertreter(object $livewire): void
{
    if (! $livewire->extendedMode && $livewire->vertreter !== '') {
        $livewire->email_vertreter = $livewire->vertreter;
        $livewire->phone_vertreter = $livewire->vertreter;
        $livewire->d3_vertreter = $livewire->vertreter;
    }
}

function buildAbwesenheitStoreData(object $livewire): AbwesenheitStoreData
{
    syncAbwesenheitVertreter($livewire);

    $emailDelegate = $livewire->email_delegate;
    if ($livewire->mailboxMustNotBeDelegated || $livewire->mailboxDelegationNotAllowed) {
        $emailDelegate = false;
    }

    $service = app(AbwesenheitService::class);
    $callDestination = null;
    if ($livewire->call_forwarding) {
        $callDestination = $service->resolveCallDestination($livewire->phone_vertreter ?: $livewire->email_vertreter);
    }

    $start = $livewire->activationMode === 'scheduled' && $livewire->start
        ? Carbon::parse($livewire->start)->startOfDay()
        : null;
    $end = $livewire->end ? Carbon::parse($livewire->end)->endOfDay() : null;

    return new AbwesenheitStoreData(
        email_vertreter: $livewire->email_vertreter,
        email_delegate: $emailDelegate,
        call_forwarding: $livewire->call_forwarding,
        d3_forwarding: $livewire->d3_forwarding,
        call_destination: $callDestination,
        start: $livewire->activationMode === 'now' ? $start : null,
        end: $end,
        notice: $livewire->notice !== '' ? $livewire->notice : null,
        phone_vertreter: $livewire->extendedMode ? $livewire->phone_vertreter : null,
        d3_vertreter: $livewire->extendedMode ? $livewire->d3_vertreter : null,
    );
}

state([
    'userId' => null,
    'abwesenheitStatus' => [],
    'activationMode' => 'now',
    'extendedMode' => false,
    'vertreter' => '',
    'email_vertreter' => '',
    'phone_vertreter' => '',
    'd3_vertreter' => '',
    'start' => null,
    'end' => null,
    'call_forwarding' => true,
    'd3_forwarding' => true,
    'email_delegate' => false,
    'notice' => '',
    'save_warning' => null,
]);

mount(function (?int $userId = null): void {
    $this->userId = $userId;
    $target = $userId ? AbwesenheitModels::userQuery()->findOrFail($userId) : auth()->user();
    Gate::authorize('abwesenheit.view', $target);
    $this->refreshAbwesenheitStatus();
});

$refreshAbwesenheitStatus = function (): void {
    $this->abwesenheitStatus = app(AbwesenheitService::class)->show($this->targetUser);
};

title(fn () => 'Abwesenheit – '.$this->targetUser->name);

$targetUser = computed(fn () => $this->userId
    ? AbwesenheitModels::userQuery()->findOrFail($this->userId)
    : auth()->user());

$pendingSchedules = computed(fn () => AbwesenheitSchedule::query()
    ->where('user_id', $this->targetUser->id)
    ->where('status', AbwesenheitScheduleStatus::Pending)
    ->orderBy('starts_at')
    ->get());

$users = computed(fn () => $this->targetUser->getGvpKollegen()->pluck('name', 'username'));

$mailboxMustNotBeDelegated = computed(fn () => $this->targetUser->beauftragtenwesen()
    ->where('mailbox_delegate', false)
    ->exists());

$mailboxDelegationNotAllowed = computed(fn () => $this->userId !== null
    && $this->userId !== auth()->id()
    && ! AbwesenheitUserPreferences::allowsSupervisorMailboxDelegationFor($this->targetUser));

$outlookStatus = computed(function () {
    $outlook = $this->abwesenheitStatus['outlook'] ?? null;
    if (is_array($outlook)) {
        return $outlook['status'] ?? 'disabled';
    }
    if (is_object($outlook) && method_exists($outlook, 'getStatus')) {
        $status = $outlook->getStatus();

        return method_exists($status, 'value') ? $status->value() : (string) $status;
    }

    return 'disabled';
});

$statusUnavailable = computed(fn () => ! empty($this->abwesenheitStatus['fetch_errors'] ?? []));

$canConfigure = computed(fn () => $this->outlookStatus === 'disabled'
    && ! ($this->abwesenheitStatus['fetch_errors']['outlook'] ?? false));

$save = function (): void {
    syncAbwesenheitVertreter($this);
    $this->save_warning = null;
    Gate::authorize('abwesenheit.manage', $this->targetUser);

    $this->validate([
        'vertreter' => 'required_if:extendedMode,false',
        'email_vertreter' => 'required_if:extendedMode,true',
        'phone_vertreter' => 'required_if:extendedMode,true',
        'd3_vertreter' => 'required_if:extendedMode,true',
        'end' => 'nullable|date',
        'start' => 'required_if:activationMode,scheduled|nullable|date|after:today',
    ], [
        'start.required_if' => 'Bitte geben Sie ein Startdatum an.',
        'start.after' => 'Das Startdatum muss in der Zukunft liegen.',
    ]);

    if ($this->activationMode === 'scheduled') {
        $this->validate([
            'end' => 'required|date|after:start',
        ]);

        if (AbwesenheitScheduleProcessor::userHasPendingSchedule($this->targetUser->id)) {
            Flux::toast(text: 'Es existiert bereits eine geplante Abwesenheit.', variant: 'danger');

            return;
        }

        if (app(AbwesenheitService::class)->isActive($this->targetUser)) {
            Flux::toast(text: 'Es ist bereits eine Abwesenheit aktiv.', variant: 'danger');

            return;
        }

        $payload = buildAbwesenheitStoreData($this)->toArray();
        $payload['start'] = Carbon::parse($this->start)->startOfDay()->toIso8601String();
        $payload['end'] = Carbon::parse($this->end)->endOfDay()->toIso8601String();

        AbwesenheitSchedule::query()->create([
            'user_id' => $this->targetUser->id,
            'created_by_user_id' => auth()->id(),
            'starts_at' => Carbon::parse($this->start)->startOfDay(),
            'ends_at' => Carbon::parse($this->end)->endOfDay(),
            'payload' => $payload,
            'status' => AbwesenheitScheduleStatus::Pending,
        ]);

        Flux::toast(text: 'Abwesenheit wurde geplant.', variant: 'success');
        unset($this->pendingSchedules);

        return;
    }

    $result = app(AbwesenheitService::class)->apply($this->targetUser, buildAbwesenheitStoreData($this));

    if ($result->warnings !== []) {
        $this->save_warning = implode(' ', $result->warnings);
    }

    Flux::toast(text: 'Abwesenheit wurde eingerichtet.', variant: 'success');
    $this->refreshAbwesenheitStatus();
};

$remove = function (): void {
    $this->save_warning = null;
    Gate::authorize('abwesenheit.manage', $this->targetUser);

    app(AbwesenheitService::class)->destroy($this->targetUser);
    app(AbwesenheitScheduleProcessor::class)->markEndedEarlyForUser($this->targetUser->id);

    Flux::toast(text: 'Abwesenheit wurde deaktiviert.', variant: 'success');
    $this->refreshAbwesenheitStatus();
};

$cancelSchedule = function (int $scheduleId): void {
    Gate::authorize('abwesenheit.manage', $this->targetUser);

    $schedule = AbwesenheitSchedule::query()
        ->where('user_id', $this->targetUser->id)
        ->where('status', AbwesenheitScheduleStatus::Pending)
        ->findOrFail($scheduleId);

    $schedule->update(['status' => AbwesenheitScheduleStatus::Cancelled]);

    Flux::toast(text: 'Geplante Abwesenheit wurde storniert.', variant: 'success');
    unset($this->pendingSchedules);
};

?>
<div>
<x-intranet-app-abwesenheit::abwesenheit-layout
    :heading="'Abwesenheit für '.$this->targetUser->name"
    subheading="Outlook, Telefon und d3"
>
    <div class="space-y-6" wire:loading.delay.class="opacity-60" wire:target="save,remove,cancelSchedule">
        @if($this->save_warning)
            <flux:callout variant="warning" icon="information-circle" heading="Hinweis zur d3-Vertretung">
                {{ $this->save_warning }}
            </flux:callout>
        @endif

        <div class="space-y-3">
            @if($this->abwesenheitStatus['fetch_errors']['outlook'] ?? false)
                <flux:callout variant="danger" icon="exclamation-triangle">Outlook-Status konnte nicht abgerufen werden.</flux:callout>
            @elseif($this->outlookStatus === 'disabled')
                <flux:callout variant="success" icon="check-circle">Keine Abwesenheit in Outlook eingerichtet</flux:callout>
            @elseif($this->outlookStatus === 'alwaysEnabled')
                <flux:callout variant="warning" icon="clock">Abwesenheit in Outlook eingerichtet (ohne Enddatum)</flux:callout>
            @elseif($this->outlookStatus === 'scheduled')
                <flux:callout variant="warning" icon="clock">
                    Abwesenheit in Outlook eingerichtet (mit Enddatum)
                    @if(is_array($this->abwesenheitStatus['outlook'] ?? null))
                        {{ $this->abwesenheitStatus['outlook']['scheduledEndDateTime']['dateTime'] ?? '' }}
                    @endif
                </flux:callout>
            @endif

            @if($this->abwesenheitStatus['fetch_errors']['phone'] ?? false)
                <flux:callout variant="danger" icon="exclamation-triangle">Telefonstatus konnte nicht abgerufen werden (Cisco AXL).</flux:callout>
            @elseif(strlen((string) ($this->abwesenheitStatus['phone'] ?? '')) > 0)
                <flux:callout variant="warning" icon="phone">Telefon umgeleitet auf {{ $this->abwesenheitStatus['phone'] }}</flux:callout>
            @else
                <flux:callout variant="success" icon="check-circle">Keine Telefonumleitung eingerichtet</flux:callout>
            @endif

            @if($this->abwesenheitStatus['fetch_errors']['d3'] ?? false)
                <flux:callout variant="danger" icon="exclamation-triangle">d3-Status konnte nicht abgerufen werden.</flux:callout>
            @elseif(($this->abwesenheitStatus['d3']['abwesend'] ?? false) == true)
                <flux:callout variant="warning" icon="inbox">
                    @if(! empty($this->abwesenheitStatus['d3']['vertreter']))
                        d3 Postfach umgeleitet auf {{ $this->abwesenheitStatus['d3']['vertreter']['vorname'] ?? '' }} {{ $this->abwesenheitStatus['d3']['vertreter']['nachname'] ?? '' }}
                    @else
                        d3 Abwesenheit aktiv (kein Vertreter hinterlegt)
                    @endif
                </flux:callout>
            @else
                <flux:callout variant="success" icon="check-circle">Keine d3 Postfachumleitung eingerichtet</flux:callout>
            @endif
        </div>

        @if($this->pendingSchedules->isNotEmpty())
            <flux:card class="space-y-4">
                <flux:heading size="md">Geplante Abwesenheiten</flux:heading>
                <div class="space-y-3">
                    @foreach($this->pendingSchedules as $schedule)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 p-4 dark:border-white/10" wire:key="schedule-{{ $schedule->id }}">
                            <div>
                                <flux:text class="font-medium">
                                    {{ $schedule->starts_at->format('d.m.Y') }} – {{ $schedule->ends_at->format('d.m.Y') }}
                                </flux:text>
                                <flux:text class="text-sm text-zinc-500 dark:text-white/50">Status: Geplant</flux:text>
                            </div>
                            <flux:button size="sm" variant="danger" wire:click="cancelSchedule({{ $schedule->id }})" wire:loading.attr="disabled">
                                Stornieren
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        @if($this->canConfigure)
            <flux:card class="space-y-6">
                <flux:heading size="lg">Abwesenheit einrichten</flux:heading>

                <flux:radio.group wire:model="activationMode" label="Aktivierung">
                    <flux:radio value="now" label="Jetzt aktivieren" />
                    <flux:radio value="scheduled" label="Planen" />
                </flux:radio.group>

                <form wire:submit="save" class="space-y-6">
                    <div
                        class="grid gap-4 md:grid-cols-2"
                        x-show="$wire.activationMode === 'scheduled'"
                        x-cloak
                        x-transition.opacity.duration.150ms
                    >
                        <flux:date-picker wire:model="start" label="Von" min="today" />
                        <flux:date-picker wire:model="end" label="Bis" />
                    </div>

                    <div
                        x-show="$wire.activationMode !== 'scheduled'"
                        x-cloak
                        x-transition.opacity.duration.150ms
                    >
                        <flux:date-picker wire:model="end" label="Bis (optional)" />
                    </div>

                    <flux:textarea wire:model="notice" label="Zusätzlicher Hinweis im Abwesenheitstext (optional)" />

                    <flux:switch wire:model="extendedMode" label="Erweitert" description="Getrennte Vertreter für E-Mail, Telefon und d3" />

                    <div
                        x-show="! $wire.extendedMode"
                        x-cloak
                        x-transition.opacity.duration.150ms
                    >
                        <flux:select wire:model="vertreter" label="Vertretung" variant="listbox" searchable placeholder="Bitte wählen" required>
                            <flux:select.option value="">Bitte wählen</flux:select.option>
                            @foreach($this->users as $username => $name)
                                <flux:select.option value="{{ $username }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div
                        class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10"
                        x-show="$wire.extendedMode"
                        x-cloak
                        x-transition.opacity.duration.150ms
                    >
                            <flux:heading size="sm">Vertreter pro Bereich</flux:heading>
                            <flux:select wire:model="email_vertreter" label="E-Mail (Outlook & Postfach)" variant="listbox" searchable placeholder="Bitte wählen" required>
                                <flux:select.option value="">Bitte wählen</flux:select.option>
                                @foreach($this->users as $username => $name)
                                    <flux:select.option value="{{ $username }}">{{ $name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="phone_vertreter" label="Telefon" variant="listbox" searchable placeholder="Bitte wählen" required>
                                <flux:select.option value="">Bitte wählen</flux:select.option>
                                @foreach($this->users as $username => $name)
                                    <flux:select.option value="{{ $username }}">{{ $name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="d3_vertreter" label="d3" variant="listbox" searchable placeholder="Bitte wählen" required>
                                <flux:select.option value="">Bitte wählen</flux:select.option>
                                @foreach($this->users as $username => $name)
                                    <flux:select.option value="{{ $username }}">{{ $name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <flux:switch wire:model="call_forwarding" label="Telefon umleiten" />
                        <flux:switch
                            wire:model="email_delegate"
                            label="E-Mail Postfach bereitstellen"
                            :disabled="$this->mailboxMustNotBeDelegated || $this->mailboxDelegationNotAllowed"
                        />
                        <flux:switch wire:model="d3_forwarding" label="d3 Postfach umleiten" />
                    </div>

                    @if($this->mailboxMustNotBeDelegated)
                        <flux:text class="text-sm text-zinc-500">Delegierung dieses Postfachs ist aufgrund des Beauftragtenwesens nicht erlaubt.</flux:text>
                    @endif
                    @if($this->mailboxDelegationNotAllowed)
                        <flux:text class="text-sm text-zinc-500">Der Mitarbeiter hat nicht zugestimmt, dass Vorgesetzte bei Abwesenheit sein Postfach delegieren dürfen.</flux:text>
                    @endif

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save" x-text="$wire.activationMode === 'scheduled' ? 'Abwesenheit planen' : 'Speichern'"></span>
                            <span wire:loading wire:target="save">Speichern…</span>
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        @else
            <div>
                <flux:button wire:click="remove" variant="danger" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="remove">Abwesenheit deaktivieren</span>
                    <span wire:loading wire:target="remove">Deaktiviere…</span>
                </flux:button>
            </div>
        @endif
    </div>
</x-intranet-app-abwesenheit::abwesenheit-layout>
</div>