<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Models\MailboxGrant;
use Hwkdo\MsGraphLaravel\Models\OutOfOfficeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

test('diagnose lists ooo-cache absences without applied schedule and without grant', function (): void {
    $candidate = User::factory()->create(['username' => 'diag.candidate', 'active' => true]);
    $withSchedule = User::factory()->create(['username' => 'diag.scheduled', 'active' => true]);
    $withGrant = User::factory()->create(['username' => 'diag.granted', 'active' => true]);
    $disabled = User::factory()->create(['username' => 'diag.disabled', 'active' => true]);

    OutOfOfficeStatus::query()->create([
        'user_id' => $candidate->id,
        'status' => 'alwaysEnabled',
        'synced_at' => now(),
    ]);
    OutOfOfficeStatus::query()->create([
        'user_id' => $withSchedule->id,
        'status' => 'alwaysEnabled',
        'synced_at' => now(),
    ]);
    OutOfOfficeStatus::query()->create([
        'user_id' => $withGrant->id,
        'status' => 'alwaysEnabled',
        'synced_at' => now(),
    ]);
    OutOfOfficeStatus::query()->create([
        'user_id' => $disabled->id,
        'status' => 'disabled',
        'synced_at' => now(),
    ]);

    AbwesenheitSchedule::query()->create([
        'user_id' => $withSchedule->id,
        'created_by_user_id' => $withSchedule->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'payload' => [],
        'status' => AbwesenheitScheduleStatus::Applied,
        'applied_at' => now()->subDay(),
    ]);

    MailboxGrant::query()->create([
        'user_id' => $withGrant->id,
        'owner_upn' => $withGrant->upn,
        'delegate_upn' => 'someone@example.test',
        'access_rights' => 'FullAccess',
        'already_existed' => false,
        'granted_at' => now(),
    ]);

    artisan('intranet-app-abwesenheit:diagnose-immediate-absences')
        ->expectsOutputToContain('diag.candidate')
        ->doesntExpectOutputToContain('diag.scheduled')
        ->doesntExpectOutputToContain('diag.granted')
        ->doesntExpectOutputToContain('diag.disabled')
        ->assertSuccessful();
});

test('diagnose reports none when ooo cache has no active absences', function (): void {
    $user = User::factory()->create(['username' => 'diag.none', 'active' => true]);

    OutOfOfficeStatus::query()->create([
        'user_id' => $user->id,
        'status' => 'disabled',
        'synced_at' => now(),
    ]);

    artisan('intranet-app-abwesenheit:diagnose-immediate-absences')
        ->expectsOutputToContain('Keine Kandidaten gefunden')
        ->assertSuccessful();
});
