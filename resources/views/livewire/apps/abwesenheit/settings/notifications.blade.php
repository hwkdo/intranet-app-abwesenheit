<?php

use function Livewire\Volt\title;

title('Benachrichtigungen - Abwesenheit');

?>

<div>
    <x-intranet-app-abwesenheit::abwesenheit-layout
        heading="Benachrichtigungen"
        subheading="Wählen Sie, wie Sie über Postfach-Zugriffe dieser App informiert werden."
    >
        <section class="glass-card overflow-hidden p-5">
            @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'abwesenheit'])
        </section>
    </x-intranet-app-abwesenheit::abwesenheit-layout>
</div>
