@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

@php
    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.abwesenheit.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen'],
        ['label' => 'Meine Abwesenheit', 'href' => route('apps.abwesenheit.meine'), 'icon' => 'calendar-days', 'description' => 'Abwesenheit einrichten oder verwalten', 'buttonText' => 'Abwesenheit öffnen'],
    ];

    if (auth()->user()?->ist_vorgesetzter) {
        $defaultNavItems[] = ['label' => 'Meine Mitarbeiter', 'href' => route('apps.abwesenheit.mitarbeiter'), 'icon' => 'users', 'description' => 'Abwesenheit für Mitarbeiter verwalten', 'buttonText' => 'Mitarbeiter öffnen'];
    }

    $defaultNavItems = array_merge($defaultNavItems, [
        ['label' => 'Meine Einstellungen', 'href' => route('apps.abwesenheit.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Postfach-Delegierung bei Abwesenheit', 'buttonText' => 'Einstellungen öffnen'],
        ['label' => 'App-Info', 'href' => route('apps.abwesenheit.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen'],
        ['label' => 'Admin', 'href' => route('apps.abwesenheit.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-abwesenheit'],
    ]);

    $navItems = ! empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('abwesenheit');
@endphp

@if($customBgUrl)
    @push('app-styles')
    <style data-app-bg data-ts="{{ uniqid() }}">
        :root { --app-bg-image: url('{{ $customBgUrl }}'); }
    </style>
    @endpush
@endif

@if(request()->routeIs('apps.abwesenheit.index'))
    <x-intranet-app-base::app-layout
        app-identifier="abwesenheit"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="false"
    >
        <x-intranet-app-base::app-index-auto
            app-identifier="abwesenheit"
            app-name="Abwesenheit"
            app-description="Abwesenheit und Vertretungen in Outlook, Telefon und d3 verwalten."
            :nav-items="$navItems"
            welcome-title="Willkommen zur Abwesenheit App"
            welcome-description="Richten Sie Ihre Abwesenheit ein, planen Sie Zeiträume im Voraus oder verwalten Sie die Abwesenheit Ihrer Mitarbeiter."
        />
    </x-intranet-app-base::app-layout>
@else
    <x-intranet-app-base::app-layout
        app-identifier="abwesenheit"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="true"
    >
        {{ $slot }}
    </x-intranet-app-base::app-layout>
@endif
