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
<x-intranet-app-abwesenheit::abwesenheit-layout heading="Meine Mitarbeiter" subheading="Abwesenheit für Mitarbeiter verwalten">
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:select wire:model.live="showAll" label="Zeige Mitarbeiter">
                <flux:select.option value="0">Nur direkt Unterstellte</flux:select.option>
                <flux:select.option value="1">Alle Unterstellten</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="selected_user" label="Mitarbeiter" variant="listbox" searchable placeholder="Bitte wählen">
                <flux:select.option value="">Bitte wählen</flux:select.option>
                @foreach($this->users as $id => $name)
                    <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if($this->selected_user)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                <livewire:apps.abwesenheit.meine
                    :user-id="(int) $this->selected_user"
                    :key="'abwesenheit-user-'.$this->selected_user"
                />
            </div>
        @endif
    </div>
</x-intranet-app-abwesenheit::abwesenheit-layout>
</div>