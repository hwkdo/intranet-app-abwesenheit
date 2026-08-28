<?php

use function Livewire\Volt\title;

title('Abwesenheit – Meine Einstellungen');

?>

<x-intranet-app-abwesenheit::abwesenheit-layout heading="Meine Einstellungen" subheading="Postfach-Delegierung bei Abwesenheit">
    @livewire('intranet-app-base::user-settings', ['appIdentifier' => 'abwesenheit'])
</x-intranet-app-abwesenheit::abwesenheit-layout>
