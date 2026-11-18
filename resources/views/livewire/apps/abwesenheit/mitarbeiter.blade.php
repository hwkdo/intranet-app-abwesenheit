<?php

declare(strict_types=1);

use function Livewire\Volt\computed;
use function Livewire\Volt\state;
use function Livewire\Volt\title;

state([
    'showAll' => false,
    'selected_user' => '',
]);

title('Abwesenheit – Meine Mitarbeiter');

$users = computed(function () {
    $untergebene = auth()->user()->getUntergebene((bool) $this->showAll);
    if ($untergebene === false) {
        return collect();
    }

    return $untergebene->pluck('name', 'id');
});

?>
<div>
    <x-intranet-app-abwesenheit::abwesenheit-layout
        heading="Meine Mitarbeiter"
        subheading="Wählen Sie eine Person und richten Sie deren Abwesenheit ein."
    >
        <div class="space-y-4">
            <section class="glass-card overflow-hidden">
                <x-intranet-app-abwesenheit::section-header
                    icon="users"
                    title="Auswahl"
                    description="Direkt unterstellte Personen oder die gesamte Struktur."
                />
                <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                    <x-intranet-app-abwesenheit::setting-row label="Zeige Mitarbeiter" description="Schränken Sie die Liste auf die direkte Ebene ein oder öffnen Sie alle Unterstellten.">
                        <flux:select wire:model.live="showAll" class="sm:w-72">
                            <flux:select.option value="0">Nur direkt Unterstellte</flux:select.option>
                            <flux:select.option value="1">Alle Unterstellten</flux:select.option>
                        </flux:select>
                    </x-intranet-app-abwesenheit::setting-row>

                    <x-intranet-app-abwesenheit::setting-row label="Mitarbeiter" description="Die Abwesenheit der gewählten Person erscheint darunter.">
                        <div class="sm:w-80">
                            <flux:select wire:model.live="selected_user" variant="listbox" searchable placeholder="Bitte wählen">
                                <flux:select.option value="">Bitte wählen</flux:select.option>
                                @foreach ($this->users as $id => $name)
                                    <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </x-intranet-app-abwesenheit::setting-row>
                </div>
            </section>

            @if ($this->users->isEmpty())
                <x-intranet-app-abwesenheit::empty-state title="Keine Mitarbeiter" icon="users">
                    In dieser Auswahl sind keine Mitarbeiter vorhanden.
                </x-intranet-app-abwesenheit::empty-state>
            @elseif ($this->selected_user === '' || $this->selected_user === null)
                <x-intranet-app-abwesenheit::empty-state title="Kein Mitarbeiter gewählt" icon="user">
                    Wählen Sie eine Person, um die Abwesenheit zu bearbeiten.
                </x-intranet-app-abwesenheit::empty-state>
            @else
                <livewire:apps.abwesenheit.meine
                    :user-id="(int) $this->selected_user"
                    :embedded="true"
                    :key="'abwesenheit-user-'.$this->selected_user"
                />
            @endif
        </div>
    </x-intranet-app-abwesenheit::abwesenheit-layout>
</div>
