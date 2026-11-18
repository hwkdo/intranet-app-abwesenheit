<?php

use function Livewire\Volt\state;
use function Livewire\Volt\title;

title('Abwesenheit - Beispiel');

state(['exampleData' => [
    'name' => 'Beispiel Item',
    'description' => 'Dies ist ein Beispiel-Item für die Abwesenheit App',
    'status' => 'active',
    'created_at' => now()->format('d.m.Y H:i'),
]]);

?>
<div>
    <x-intranet-app-abwesenheit::abwesenheit-layout
        heading="Beispiel"
        subheading="So ist eine Seite der Abwesenheits-App aufgebaut."
    >
        <div class="space-y-4">
            <section class="glass-card overflow-hidden">
                <x-intranet-app-abwesenheit::section-header
                    icon="document-text"
                    title="{{ $exampleData['name'] }}"
                    description="{{ $exampleData['description'] }}"
                />
                <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                    <x-intranet-app-abwesenheit::setting-row label="Status" :description="$exampleData['created_at']">
                        <x-intranet-app-abwesenheit::status-pill active>{{ $exampleData['status'] }}</x-intranet-app-abwesenheit::status-pill>
                    </x-intranet-app-abwesenheit::setting-row>
                </div>
            </section>

            <section class="glass-card overflow-hidden">
                <x-intranet-app-abwesenheit::section-header
                    icon="information-circle"
                    title="Weitere Informationen"
                    description="Hier könnten weitere Details oder Aktionen für das Beispiel-Item angezeigt werden."
                />
                <div class="flex flex-wrap justify-end gap-2 px-5 py-4">
                    <flux:button variant="primary" size="sm">Bearbeiten</flux:button>
                    <flux:button variant="ghost" size="sm">Löschen</flux:button>
                </div>
            </section>
        </div>
    </x-intranet-app-abwesenheit::abwesenheit-layout>
</div>
