@php
    $outlookError = (bool) ($this->abwesenheitStatus['fetch_errors']['outlook'] ?? false);
    $phoneError = (bool) ($this->abwesenheitStatus['fetch_errors']['phone'] ?? false);
    $d3Error = (bool) ($this->abwesenheitStatus['fetch_errors']['d3'] ?? false);

    if ($outlookError) {
        $outlookText = 'Outlook-Status konnte nicht abgerufen werden.';
        $outlookPill = 'Fehler';
        $outlookActive = true;
    } elseif ($this->outlookStatus === 'alwaysEnabled') {
        $outlookText = 'Abwesenheit in Outlook eingerichtet (ohne Enddatum)';
        $outlookPill = 'Aktiv';
        $outlookActive = true;
    } elseif ($this->outlookStatus === 'scheduled') {
        $outlookEnd = '';
        if (is_array($this->abwesenheitStatus['outlook'] ?? null)) {
            $outlookEnd = (string) ($this->abwesenheitStatus['outlook']['scheduledEndDateTime']['dateTime'] ?? '');
        }
        $outlookText = trim('Abwesenheit in Outlook eingerichtet (mit Enddatum) '.$outlookEnd);
        $outlookPill = 'Aktiv';
        $outlookActive = true;
    } else {
        $outlookText = 'Keine Abwesenheit in Outlook eingerichtet';
        $outlookPill = 'Aus';
        $outlookActive = false;
    }

    if ($phoneError) {
        $phoneText = 'Telefonstatus konnte nicht abgerufen werden (Cisco AXL).';
        $phonePill = 'Fehler';
        $phoneActive = true;
    } elseif (strlen((string) ($this->abwesenheitStatus['phone'] ?? '')) > 0) {
        $phoneText = 'Telefon umgeleitet auf '.$this->abwesenheitStatus['phone'];
        $phonePill = 'Aktiv';
        $phoneActive = true;
    } else {
        $phoneText = 'Keine Telefonumleitung eingerichtet';
        $phonePill = 'Aus';
        $phoneActive = false;
    }

    $d3 = is_array($this->abwesenheitStatus['d3'] ?? null) ? $this->abwesenheitStatus['d3'] : [];
    if ($d3Error) {
        $d3Text = 'd3-Status konnte nicht abgerufen werden.';
        $d3Pill = 'Fehler';
        $d3Active = true;
    } elseif (($d3['abwesend'] ?? false) == true && ! empty($d3['vertreter'])) {
        $d3Text = 'd3 Postfach umgeleitet auf '.trim(($d3['vertreter']['vorname'] ?? '').' '.($d3['vertreter']['nachname'] ?? ''));
        $d3Pill = 'Aktiv';
        $d3Active = true;
    } elseif (($d3['abwesend'] ?? false) == true) {
        $d3Text = 'd3 Abwesenheit aktiv (kein Vertreter hinterlegt)';
        $d3Pill = 'Aktiv';
        $d3Active = true;
    } else {
        $d3Text = 'Keine d3 Postfachumleitung eingerichtet';
        $d3Pill = 'Aus';
        $d3Active = false;
    }

    $mailboxDelegates = $this->abwesenheitStatus['mailbox']['delegates'] ?? [];
    $mailboxDelegated = (bool) ($this->abwesenheitStatus['mailbox']['delegated'] ?? false);
    $mailboxDelegateNames = collect($mailboxDelegates)->pluck('name')->filter()->implode(', ');

    if ($mailboxDelegated && $mailboxDelegateNames !== '') {
        $mailboxText = 'Postfach delegiert an '.$mailboxDelegateNames;
        $mailboxPill = 'Aktiv';
        $mailboxActive = true;
    } elseif ($mailboxDelegated) {
        $mailboxText = 'Postfach-Delegierung aktiv';
        $mailboxPill = 'Aktiv';
        $mailboxActive = true;
    } else {
        $mailboxText = 'Keine Postfach-Delegierung eingerichtet';
        $mailboxPill = 'Aus';
        $mailboxActive = false;
    }

    if ($this->mailboxMustNotBeDelegated) {
        $mailboxSwitchDescription = 'Delegierung dieses Postfachs ist aufgrund des Beauftragtenwesens nicht erlaubt.';
    } elseif ($this->mailboxDelegationNotAllowed) {
        $mailboxSwitchDescription = 'Der Mitarbeiter hat nicht zugestimmt, dass Vorgesetzte bei Abwesenheit sein Postfach delegieren dürfen.';
    } else {
        $mailboxSwitchDescription = 'Die Vertretung darf das Postfach öffnen.';
    }

    $channels = [
        ['label' => 'Outlook', 'text' => $outlookText, 'pill' => $outlookPill, 'active' => $outlookActive],
        ['label' => 'Telefon', 'text' => $phoneText, 'pill' => $phonePill, 'active' => $phoneActive],
        ['label' => 'd3', 'text' => $d3Text, 'pill' => $d3Pill, 'active' => $d3Active],
        ['label' => 'Postfach', 'text' => $mailboxText, 'pill' => $mailboxPill, 'active' => $mailboxActive],
    ];
@endphp

<div class="space-y-4" wire:loading.delay.class="opacity-60" wire:target="save,remove,cancelSchedule">
    @if ($this->save_warning)
        <section class="glass-card overflow-hidden">
            <div class="flex items-start gap-3 px-5 py-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                    <flux:icon.information-circle variant="micro" class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-[#073070] dark:text-white">Hinweis</h2>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-white/70">{{ $this->save_warning }}</p>
                </div>
            </div>
        </section>
    @endif

    <section class="glass-card overflow-hidden">
        <x-intranet-app-abwesenheit::section-header
            icon="calendar-days"
            title="Aktueller Status"
            description="Outlook, Telefon, d3 und Postfach für {{ $this->targetUser->name }}."
        />
        <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
            @foreach ($channels as $channel)
                <x-intranet-app-abwesenheit::setting-row :label="$channel['label']" :description="$channel['text']">
                    <x-intranet-app-abwesenheit::status-pill :active="$channel['active']">{{ $channel['pill'] }}</x-intranet-app-abwesenheit::status-pill>
                </x-intranet-app-abwesenheit::setting-row>
            @endforeach
        </div>
    </section>

    @if ($this->pendingSchedules->isNotEmpty())
        <section class="glass-card overflow-hidden">
            <x-intranet-app-abwesenheit::section-header
                icon="clock"
                title="Geplante Abwesenheiten"
                description="Zeiträume, die noch nicht gestartet sind."
            />
            <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                @foreach ($this->pendingSchedules as $schedule)
                    <x-intranet-app-abwesenheit::setting-row
                        wire:key="schedule-{{ $schedule->id }}"
                        :label="$schedule->starts_at->format('d.m.Y').' – '.$schedule->ends_at->format('d.m.Y')"
                        description="Status: Geplant"
                    >
                        <flux:button size="sm" variant="danger" wire:click="cancelSchedule({{ $schedule->id }})" wire:loading.attr="disabled">
                            Stornieren
                        </flux:button>
                    </x-intranet-app-abwesenheit::setting-row>
                @endforeach
            </div>
        </section>
    @endif

    @if ($this->canConfigure)
        <section class="glass-card overflow-hidden">
            <x-intranet-app-abwesenheit::section-header
                icon="pencil-square"
                title="Abwesenheit einrichten"
                description="Vertretung, Zeitraum und Kanäle legen Sie hier fest."
            />

            <form wire:submit="save">
                <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                    <x-intranet-app-abwesenheit::setting-row label="Aktivierung" description="Sofort starten oder einen Zeitraum im Voraus planen.">
                        <div class="flex flex-wrap justify-end gap-1.5" role="group" aria-label="Aktivierung">
                            <button
                                type="button"
                                wire:click="$set('activationMode', 'now')"
                                @class([
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]',
                                    'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $activationMode === 'now',
                                    'bg-white/80 text-[#073070] ring-1 ring-[#d0e3f9] dark:bg-white/10 dark:text-white dark:ring-white/15' => $activationMode !== 'now',
                                ])
                            >Jetzt aktivieren</button>
                            <button
                                type="button"
                                wire:click="$set('activationMode', 'scheduled')"
                                @class([
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#073070]',
                                    'bg-[#073070] text-white shadow-md shadow-[#073070]/25' => $activationMode === 'scheduled',
                                    'bg-white/80 text-[#073070] ring-1 ring-[#d0e3f9] dark:bg-white/10 dark:text-white dark:ring-white/15' => $activationMode !== 'scheduled',
                                ])
                            >Planen</button>
                        </div>
                    </x-intranet-app-abwesenheit::setting-row>

                    <div x-show="$wire.activationMode === 'scheduled'" x-cloak class="divide-y divide-[#d0e3f9] dark:divide-white/10">
                        <x-intranet-app-abwesenheit::setting-row label="Von" description="Das Startdatum muss in der Zukunft liegen.">
                            <div class="sm:w-64">
                                <flux:date-picker wire:model="start" min="today" placeholder="Datum wählen" />
                                <flux:error name="start" />
                            </div>
                        </x-intranet-app-abwesenheit::setting-row>
                        <x-intranet-app-abwesenheit::setting-row label="Bis">
                            <div class="sm:w-64">
                                <flux:date-picker wire:model="end" placeholder="Datum wählen" />
                                <flux:error name="end" />
                            </div>
                        </x-intranet-app-abwesenheit::setting-row>
                    </div>

                    <div x-show="$wire.activationMode !== 'scheduled'" x-cloak>
                        <x-intranet-app-abwesenheit::setting-row label="Bis (optional)" description="Ohne Enddatum bleibt die Abwesenheit aktiv, bis Sie sie beenden.">
                            <div class="sm:w-64">
                                <flux:date-picker wire:model="end" placeholder="Datum wählen" />
                                <flux:error name="end" />
                            </div>
                        </x-intranet-app-abwesenheit::setting-row>
                    </div>

                    <x-intranet-app-abwesenheit::setting-row label="Zusätzlicher Hinweis im Abwesenheitstext (optional)" wide>
                        <flux:textarea wire:model="notice" />
                    </x-intranet-app-abwesenheit::setting-row>

                    <x-intranet-app-abwesenheit::setting-row label="Erweitert" description="Getrennte Vertreter für E-Mail, Telefon und d3">
                        <flux:switch wire:model.live="extendedMode" align="left" />
                    </x-intranet-app-abwesenheit::setting-row>

                    <x-intranet-app-abwesenheit::setting-row label="Telefon umleiten" description="Anrufe gehen an die Telefon-Vertretung.">
                        <flux:switch wire:model.live="call_forwarding" align="left" />
                    </x-intranet-app-abwesenheit::setting-row>

                    <x-intranet-app-abwesenheit::setting-row label="E-Mail Postfach bereitstellen" :description="$mailboxSwitchDescription">
                        <flux:switch
                            wire:model.live="email_delegate"
                            align="left"
                            :disabled="$this->mailboxMustNotBeDelegated || $this->mailboxDelegationNotAllowed"
                        />
                    </x-intranet-app-abwesenheit::setting-row>

                    <x-intranet-app-abwesenheit::setting-row label="d3 Postfach umleiten" description="Dokumente im d3-Postfach gehen an die Vertretung.">
                        <flux:switch wire:model.live="d3_forwarding" align="left" />
                    </x-intranet-app-abwesenheit::setting-row>

                    <div x-show="! $wire.extendedMode" x-cloak>
                        <x-intranet-app-abwesenheit::setting-row label="Vertretung" description="Eine Person für E-Mail, Telefon und d3.">
                            <div class="sm:w-80">
                                <flux:select wire:model="vertreter" variant="listbox" searchable placeholder="Bitte wählen" required>
                                    <flux:select.option value="">Bitte wählen</flux:select.option>
                                    @foreach ($this->users as $username => $name)
                                        <flux:select.option value="{{ $username }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="vertreter" />
                            </div>
                        </x-intranet-app-abwesenheit::setting-row>
                    </div>

                    <div x-show="$wire.extendedMode" x-cloak class="divide-y divide-[#d0e3f9] dark:divide-white/10">
                        <x-intranet-app-abwesenheit::setting-row label="E-Mail (Outlook & Postfach)">
                            <div class="sm:w-80">
                                <flux:select wire:model="email_vertreter" variant="listbox" searchable placeholder="Bitte wählen" required>
                                    <flux:select.option value="">Bitte wählen</flux:select.option>
                                    @foreach ($this->users as $username => $name)
                                        <flux:select.option value="{{ $username }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="email_vertreter" />
                            </div>
                        </x-intranet-app-abwesenheit::setting-row>

                        @if ($call_forwarding)
                            <x-intranet-app-abwesenheit::setting-row label="Telefon">
                                <div class="sm:w-80">
                                    <flux:select wire:model="phone_vertreter" variant="listbox" searchable placeholder="Bitte wählen" required>
                                        <flux:select.option value="">Bitte wählen</flux:select.option>
                                        @foreach ($this->users as $username => $name)
                                            <flux:select.option value="{{ $username }}">{{ $name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="phone_vertreter" />
                                </div>
                            </x-intranet-app-abwesenheit::setting-row>
                        @endif

                        @if ($d3_forwarding)
                            <x-intranet-app-abwesenheit::setting-row label="d3">
                                <div class="sm:w-80">
                                    <flux:select wire:model="d3_vertreter" variant="listbox" searchable placeholder="Bitte wählen" required>
                                        <flux:select.option value="">Bitte wählen</flux:select.option>
                                        @foreach ($this->users as $username => $name)
                                            <flux:select.option value="{{ $username }}">{{ $name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="d3_vertreter" />
                                </div>
                            </x-intranet-app-abwesenheit::setting-row>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end border-t border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save" x-text="$wire.activationMode === 'scheduled' ? 'Abwesenheit planen' : 'Speichern'"></span>
                        <span wire:loading wire:target="save">Speichern…</span>
                    </flux:button>
                </div>
            </form>
        </section>
    @else
        <section class="glass-card overflow-hidden">
            <x-intranet-app-abwesenheit::section-header
                icon="x-circle"
                title="Abwesenheit beenden"
                description="Outlook, Telefon, d3 und Postfach werden auf den normalen Stand zurückgesetzt."
            />
            <div class="flex justify-end px-5 py-4">
                <flux:button wire:click="remove" variant="danger" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="remove">Abwesenheit deaktivieren</span>
                    <span wire:loading wire:target="remove">Wird deaktiviert…</span>
                </flux:button>
            </div>
        </section>
    @endif
</div>
