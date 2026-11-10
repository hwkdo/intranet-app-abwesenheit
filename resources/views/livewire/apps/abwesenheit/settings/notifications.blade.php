<?php

use function Livewire\Volt\title;

title('Benachrichtigungen - Abwesenheit');

?>

<div>
    <x-intranet-app-abwesenheit::abwesenheit-layout heading="Benachrichtigungen" subheading="Benachrichtigungseinstellungen für die Abwesenheits-App">
        @livewire('intranet-app-base::notification-settings', ['appIdentifier' => 'abwesenheit'])
    </x-intranet-app-abwesenheit::abwesenheit-layout>
</div>
