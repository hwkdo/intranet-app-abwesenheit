<?php

use function Livewire\Volt\title;

title('Abwesenheit - App-Info');

?>
<div>
    <x-intranet-app-abwesenheit::abwesenheit-layout
        heading="App-Info"
        subheading="Installierte Version und Release-Historie dieser App."
    >
        <section class="glass-card overflow-hidden p-5">
            @livewire('intranet-app-base::app-info', ['appIdentifier' => 'abwesenheit'])
        </section>
    </x-intranet-app-abwesenheit::abwesenheit-layout>
</div>
