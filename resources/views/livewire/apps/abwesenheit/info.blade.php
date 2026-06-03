<?php

use function Livewire\Volt\{title};

title('Abwesenheit - App-Info');

?>

<x-intranet-app-abwesenheit::abwesenheit-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
    @livewire('intranet-app-base::app-info', ['appIdentifier' => 'abwesenheit'])
</x-intranet-app-abwesenheit::abwesenheit-layout>
