<?php

declare(strict_types=1);

use App\Models\Gvp;
use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Policies\AbwesenheitPolicy;
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
