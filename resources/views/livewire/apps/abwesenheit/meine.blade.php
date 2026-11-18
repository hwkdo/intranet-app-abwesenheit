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
use Illuminate\Validation\Rule;
use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;
use function Livewire\Volt\title;

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
    'embedded' => false,
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
    $storeData = function (): AbwesenheitStoreData {
        if (! $this->extendedMode && $this->vertreter !== '') {
            $this->email_vertreter = $this->vertreter;
            $this->phone_vertreter = $this->vertreter;
            $this->d3_vertreter = $this->vertreter;
        }

        $emailDelegate = $this->email_delegate;
        if ($this->mailboxMustNotBeDelegated || $this->mailboxDelegationNotAllowed) {
            $emailDelegate = false;
        }

        $service = app(AbwesenheitService::class);
        $callDestination = null;
        if ($this->call_forwarding) {
            $callDestination = $service->resolveCallDestination($this->phone_vertreter ?: $this->email_vertreter);
        }

        $start = $this->activationMode === 'scheduled' && $this->start
            ? Carbon::parse($this->start)->startOfDay()
            : null;
        $end = $this->end ? Carbon::parse($this->end)->endOfDay() : null;

        return new AbwesenheitStoreData(
            email_vertreter: $this->email_vertreter,
            email_delegate: $emailDelegate,
            call_forwarding: $this->call_forwarding,
            d3_forwarding: $this->d3_forwarding,
            call_destination: $callDestination,
            start: $this->activationMode === 'now' ? $start : null,
            end: $end,
            notice: $this->notice !== '' ? $this->notice : null,
            phone_vertreter: $this->extendedMode ? $this->phone_vertreter : null,
            d3_vertreter: $this->extendedMode ? $this->d3_vertreter : null,
        );
    };

    $this->save_warning = null;
    Gate::authorize('abwesenheit.manage', $this->targetUser);

    $this->validate([
        'vertreter' => 'required_if:extendedMode,false',
        'email_vertreter' => 'required_if:extendedMode,true',
        'phone_vertreter' => [
            Rule::requiredIf(fn (): bool => $this->extendedMode && $this->call_forwarding),
        ],
        'd3_vertreter' => [
            Rule::requiredIf(fn (): bool => $this->extendedMode && $this->d3_forwarding),
        ],
        'end' => 'nullable|date',
        'start' => 'required_if:activationMode,scheduled|nullable|date|after:today',
    ], [
        'start.required_if' => 'Bitte geben Sie ein Startdatum an.',
        'start.after' => 'Das Startdatum muss in der Zukunft liegen.',
        'phone_vertreter.required' => 'Bitte wählen Sie einen Telefon-Vertreter.',
        'd3_vertreter.required' => 'Bitte wählen Sie einen d3-Vertreter.',
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

        $payload = $storeData()->toArray();
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

    $result = app(AbwesenheitService::class)->apply($this->targetUser, $storeData());

    if ($result->warnings !== []) {
        $this->save_warning = implode(' ', $result->warnings);
    }

    Flux::toast(text: 'Abwesenheit wurde eingerichtet.', variant: 'success');
    $this->refreshAbwesenheitStatus();
};

$remove = function (): void {
    $this->save_warning = null;
    Gate::authorize('abwesenheit.manage', $this->targetUser);

    $result = app(AbwesenheitService::class)->destroy($this->targetUser);

    if ($result->warnings !== []) {
        $this->save_warning = implode(' ', $result->warnings);
    }
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
    @if ($embedded)
        @include('intranet-app-abwesenheit::livewire.apps.abwesenheit.partials.formular')
    @else
        <x-intranet-app-abwesenheit::abwesenheit-layout
            :heading="'Abwesenheit für '.$this->targetUser->name"
            subheading="Outlook, Telefon, d3 und Postfach"
        >
            @include('intranet-app-abwesenheit::livewire.apps.abwesenheit.partials.formular')
        </x-intranet-app-abwesenheit::abwesenheit-layout>
    @endif
</div>