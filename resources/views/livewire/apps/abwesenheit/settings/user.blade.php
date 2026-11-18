<?php

use function Livewire\Volt\title;

title('Abwesenheit – Meine Einstellungen');

?>
<div>
    <x-intranet-app-abwesenheit::abwesenheit-layout
        heading="Meine Einstellungen"
        subheading="Legen Sie fest, ob Vorgesetzte bei Ihrer Abwesenheit das Postfach delegieren dürfen."
    >
        <section class="glass-card overflow-hidden p-5">
            @livewire('intranet-app-base::user-settings', ['appIdentifier' => 'abwesenheit'])
        </section>
    </x-intranet-app-abwesenheit::abwesenheit-layout>
</div>
