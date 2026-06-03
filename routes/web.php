<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['web','auth','can:see-app-abwesenheit'])->group(function () {        
    Volt::route('apps/abwesenheit', 'apps.abwesenheit.index')->name('apps.abwesenheit.index');
    Volt::route('apps/abwesenheit/example', 'apps.abwesenheit.example')->name('apps.abwesenheit.example');
    Volt::route('apps/abwesenheit/settings/user', 'apps.abwesenheit.settings.user')->name('apps.abwesenheit.settings.user');
    Volt::route('apps/abwesenheit/info', 'apps.abwesenheit.info')->name('apps.abwesenheit.info');
});

Route::middleware(['web','auth','can:manage-app-abwesenheit'])->group(function () {
    Volt::route('apps/abwesenheit/admin', 'apps.abwesenheit.admin.index')->name('apps.abwesenheit.admin.index');
});
