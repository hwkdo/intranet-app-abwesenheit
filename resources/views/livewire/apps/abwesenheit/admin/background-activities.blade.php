<?php

use function Livewire\Volt\title;

title('Hintergrund');

?>

<div>
    <x-intranet-app-abwesenheit::abwesenheit-layout heading="Hintergrund" subheading="Geplante Tasks, Jobs, Mails und Benachrichtigungen">
        @livewire('intranet-app-base::background-activities', ['appIdentifier' => 'abwesenheit'])
    </x-intranet-app-abwesenheit::abwesenheit-layout>
</div>
