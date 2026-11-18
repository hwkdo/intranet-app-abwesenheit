<?php

use function Livewire\Volt\state;
use function Livewire\Volt\title;

title('Abwesenheit - Admin');

state(['activeTab' => 'hintergrundbild'])->url(as: 'tab');

?>
<div>
    <x-intranet-app-abwesenheit::abwesenheit-layout
        heading="Verwaltung"
        subheading="Hintergrundbild, Einstellungen, Kennzahlen und Hintergrundaktivitäten."
    >
        @php
            $adminTabs = [
                ['name' => 'hintergrundbild', 'label' => 'Hintergrundbild', 'icon' => 'photo'],
                ['name' => 'einstellungen', 'label' => 'Einstellungen', 'icon' => 'cog-6-tooth'],
                ['name' => 'statistiken', 'label' => 'Statistiken', 'icon' => 'chart-bar'],
                ['name' => 'hintergrund', 'label' => 'Hintergrund', 'icon' => 'clock'],
            ];
        @endphp

        <flux:tab.group>
            <div class="glass-card sticky top-3 z-20 p-3 shadow-lg shadow-[#073070]/10" style="--glass-opacity: 0.92">
                <flux:tabs variant="pills" class="hwk-pills" wire:model.live="activeTab">
                    @foreach ($adminTabs as $tab)
                        <flux:tab :name="$tab['name']" :icon="$tab['icon']">{{ $tab['label'] }}</flux:tab>
                    @endforeach
                </flux:tabs>
            </div>

            <flux:tab.panel name="hintergrundbild" class="pt-4">
                <section class="glass-card min-h-[400px] overflow-hidden p-5">
                    @livewire('intranet-app-base::app-background-image', [
                        'appIdentifier' => 'abwesenheit',
                    ])
                </section>
            </flux:tab.panel>

            <flux:tab.panel name="einstellungen" class="pt-4">
                <section class="glass-card min-h-[400px] overflow-hidden p-5">
                    @livewire('intranet-app-base::admin-settings', [
                        'appIdentifier' => 'abwesenheit',
                        'settingsModelClass' => '\Hwkdo\IntranetAppAbwesenheit\Models\IntranetAppAbwesenheitSettings',
                        'appSettingsClass' => '\Hwkdo\IntranetAppAbwesenheit\Data\AppSettings',
                    ])
                </section>
            </flux:tab.panel>

            <flux:tab.panel name="statistiken" class="pt-4">
                <section class="glass-card min-h-[400px] overflow-hidden">
                    <x-intranet-app-abwesenheit::section-header
                        icon="chart-bar"
                        title="App-Statistiken"
                        description="Übersicht über die Nutzung der Abwesenheit App."
                    />
                    <div class="grid gap-4 p-5 md:grid-cols-3">
                        <div class="rounded-xl bg-white/70 px-4 py-4 ring-1 ring-[#d0e3f9] dark:bg-white/5 dark:ring-white/10">
                            <p class="text-sm font-medium text-[#073070] dark:text-white">Aktive Benutzer</p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-[#073070] dark:text-white">42</p>
                        </div>
                        <div class="rounded-xl bg-white/70 px-4 py-4 ring-1 ring-[#d0e3f9] dark:bg-white/5 dark:ring-white/10">
                            <p class="text-sm font-medium text-[#073070] dark:text-white">Seitenaufrufe</p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-[#073070] dark:text-white">1,234</p>
                        </div>
                        <div class="rounded-xl bg-white/70 px-4 py-4 ring-1 ring-[#d0e3f9] dark:bg-white/5 dark:ring-white/10">
                            <p class="text-sm font-medium text-[#073070] dark:text-white">Letzte Aktivität</p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-[#073070] dark:text-white">2 Min</p>
                        </div>
                    </div>
                </section>
            </flux:tab.panel>

            <flux:tab.panel name="hintergrund" class="pt-4">
                @if ($activeTab === 'hintergrund')
                    <section class="glass-card min-h-[400px] overflow-hidden p-5">
                        @livewire('intranet-app-base::background-activities', ['appIdentifier' => 'abwesenheit'], key('abwesenheit-admin-hintergrund'))
                    </section>
                @endif
            </flux:tab.panel>
        </flux:tab.group>
    </x-intranet-app-abwesenheit::abwesenheit-layout>
</div>
