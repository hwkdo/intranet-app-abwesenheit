<?php

declare(strict_types=1);

use App\Models\Gvp;
use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Data\UserSettings;
use Hwkdo\IntranetAppAbwesenheit\Policies\AbwesenheitPolicy;
use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitUserPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can manage own abwesenheit', function (): void {
    $user = User::factory()->create(['active' => true]);
    $policy = new AbwesenheitPolicy;

    expect($policy->manage($user, $user))->toBeTrue();
});

test('vorgesetzter can manage untergebene in same gvp', function (): void {
    $gvp = Gvp::factory()->create();
    $vorgesetzter = User::factory()->create(['gvp_id' => $gvp->id, 'active' => true]);
    $gvp->update(['vorgesetzter_id' => $vorgesetzter->id]);

    $mitarbeiter = User::factory()->create(['gvp_id' => $gvp->id, 'active' => true]);

    $policy = new AbwesenheitPolicy;

    expect($vorgesetzter->ist_vorgesetzter)->toBeTrue()
        ->and($policy->manage($vorgesetzter, $mitarbeiter))->toBeTrue();
});

test('non supervisor cannot manage other users', function (): void {
    $gvp = Gvp::factory()->create();
    $user = User::factory()->create(['gvp_id' => $gvp->id, 'active' => true]);
    $other = User::factory()->create(['gvp_id' => $gvp->id, 'active' => true]);

    $policy = new AbwesenheitPolicy;

    expect($policy->manage($user, $other))->toBeFalse();
});

test('vorgesetzter can manage child gvp supervisor recursively', function (): void {
    $gb = Gvp::factory()->create(['kuerzel' => 'GB']);
    $abteilung = Gvp::factory()->create(['kuerzel' => 'A', 'parent_id' => $gb->id]);

    $geschaeftsfuehrer = User::factory()->create(['gvp_id' => $gb->id, 'active' => true]);
    $abteilungsleiterin = User::factory()->create(['gvp_id' => $abteilung->id, 'active' => true]);

    $gb->update(['vorgesetzter_id' => $geschaeftsfuehrer->id]);
    $abteilung->update(['vorgesetzter_id' => $abteilungsleiterin->id]);

    $policy = new AbwesenheitPolicy;

    expect($policy->manage($geschaeftsfuehrer, $abteilungsleiterin))->toBeTrue();
});

test('vorgesetzter cannot delegate mailbox when mitarbeiter has not allowed it', function (): void {
    $gvp = Gvp::factory()->create();
    $vorgesetzter = User::factory()->create(['gvp_id' => $gvp->id, 'active' => true]);
    $gvp->update(['vorgesetzter_id' => $vorgesetzter->id]);

    $mitarbeiter = User::factory()->create(['gvp_id' => $gvp->id, 'active' => true]);

    $policy = new AbwesenheitPolicy;

    expect($policy->delegateMailboxFor($vorgesetzter, $mitarbeiter))->toBeFalse();
});

test('vorgesetzter can delegate mailbox when mitarbeiter allowed it in user settings', function (): void {
    $gvp = Gvp::factory()->create();
    $vorgesetzter = User::factory()->create(['gvp_id' => $gvp->id, 'active' => true]);
    $gvp->update(['vorgesetzter_id' => $vorgesetzter->id]);

    $mitarbeiter = User::factory()->create(['gvp_id' => $gvp->id, 'active' => true]);
    $mitarbeiter->settings = $mitarbeiter->settings->updateAppSettings('abwesenheit', [
        'allowSupervisorMailboxDelegation' => true,
    ]);
    $mitarbeiter->save();

    $policy = new AbwesenheitPolicy;

    expect($policy->delegateMailboxFor($vorgesetzter, $mitarbeiter->fresh()))->toBeTrue();
});

test('user always can delegate own mailbox', function (): void {
    $user = User::factory()->create(['active' => true]);
    $policy = new AbwesenheitPolicy;

    expect($policy->delegateMailboxFor($user, $user))->toBeTrue();
});

test('abwesenheit user settings default to no mailbox delegation', function (): void {
    $user = User::factory()->create(['active' => true]);

    expect($user->settings->app->abwesenheit)->toBeInstanceOf(UserSettings::class)
        ->and($user->settings->app->abwesenheit->allowSupervisorMailboxDelegation)->toBeFalse()
        ->and(AbwesenheitUserPreferences::allowsSupervisorMailboxDelegationFor($user))->toBeFalse();
});

test('legacy defaultViewMode in stored abwesenheit settings is ignored', function (): void {
    $settings = UserSettings::from([
        'defaultViewMode' => 'grid',
        'allowSupervisorMailboxDelegation' => true,
    ]);

    expect($settings->allowSupervisorMailboxDelegation)->toBeTrue()
        ->and($settings->toArray())->not->toHaveKey('defaultViewMode');
});
