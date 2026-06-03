<?php

declare(strict_types=1);

use Flux\Flux;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;
use function Livewire\Volt\title;

state([
    'allow_supervisor_mailbox_delegation' => false,
]);

mount(function (): void {
    $this->allow_supervisor_mailbox_delegation = (bool) auth()->user()->allow_supervisor_mailbox_delegation;
});

title('Abwesenheit – Meine Einstellungen');

$save = function (): void {
    $user = auth()->user();
    $user->allow_supervisor_mailbox_delegation = $this->allow_supervisor_mailbox_delegation;
    $user->save();

    Flux::toast(text: 'Einstellungen gespeichert.', variant: 'success');
};

?>

<x-intranet-app-abwesenheit::abwesenheit-layout heading="Meine Einstellungen" subheading="Postfach-Delegierung bei Abwesenheit">
    <flux:card class="max-w-xl space-y-6">
        <flux:heading size="md">Postfach-Delegierung</flux:heading>

        <form wire:submit="save" class="space-y-4">
            <flux:switch
                wire:model="allow_supervisor_mailbox_delegation"
                label="Vorgesetzten erlauben, bei Abwesenheit mein Postfach zu delegieren"
                description="Wenn aktiviert, können Ihre Vorgesetzten bei Ihrer Abwesenheit Ihr Postfach an einen Vertreter delegieren."
            />

            <flux:button type="submit" variant="primary">Speichern</flux:button>
        </form>
    </flux:card>
</x-intranet-app-abwesenheit::abwesenheit-layout>
