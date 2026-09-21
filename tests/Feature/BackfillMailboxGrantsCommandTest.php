<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Models\MailboxGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

test('backfill creates grants for applied schedules with email_delegate', function (): void {
    $owner = User::factory()->create(['username' => 'backfill.owner', 'active' => true]);
    $delegate = User::factory()->create(['username' => 'backfill.delegate', 'active' => true]);

    $schedule = AbwesenheitSchedule::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'payload' => [
            'email_vertreter' => $delegate->username,
            'email_delegate' => true,
            'call_forwarding' => false,
            'd3_forwarding' => false,
        ],
        'status' => AbwesenheitScheduleStatus::Applied,
        'applied_at' => now()->subDay(),
    ]);

    artisan('intranet-app-abwesenheit:backfill-mailbox-grants')
        ->assertSuccessful();

    $grant = MailboxGrant::query()->where('user_id', $owner->id)->first();

    expect($grant)->not->toBeNull()
        ->and($grant->delegate_upn)->toBe($delegate->upn)
        ->and($grant->already_existed)->toBeFalse()
        ->and($grant->schedule_id)->toBe($schedule->id)
        ->and($grant->revoked_at)->toBeNull();
});

test('backfill dry-run does not persist grants', function (): void {
    $owner = User::factory()->create(['username' => 'dryrun.owner', 'active' => true]);
    $delegate = User::factory()->create(['username' => 'dryrun.delegate', 'active' => true]);

    AbwesenheitSchedule::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'payload' => [
            'email_vertreter' => $delegate->username,
            'email_delegate' => true,
            'call_forwarding' => false,
            'd3_forwarding' => false,
        ],
        'status' => AbwesenheitScheduleStatus::Applied,
        'applied_at' => now()->subDay(),
    ]);

    artisan('intranet-app-abwesenheit:backfill-mailbox-grants', ['--dry-run' => true])
        ->assertSuccessful();

    expect(MailboxGrant::query()->count())->toBe(0);
});

test('backfill skips schedules without email_delegate and existing grants', function (): void {
    $owner = User::factory()->create(['username' => 'skip.owner', 'active' => true]);
    $delegate = User::factory()->create(['username' => 'skip.delegate', 'active' => true]);

    AbwesenheitSchedule::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'payload' => [
            'email_vertreter' => $delegate->username,
            'email_delegate' => false,
            'call_forwarding' => false,
            'd3_forwarding' => false,
        ],
        'status' => AbwesenheitScheduleStatus::Applied,
        'applied_at' => now()->subDay(),
    ]);

    MailboxGrant::query()->create([
        'user_id' => $owner->id,
        'owner_upn' => $owner->upn,
        'delegate_upn' => $delegate->upn,
        'access_rights' => 'FullAccess',
        'already_existed' => false,
        'granted_at' => now(),
    ]);

    AbwesenheitSchedule::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'payload' => [
            'email_vertreter' => $delegate->username,
            'email_delegate' => true,
            'call_forwarding' => false,
            'd3_forwarding' => false,
        ],
        'status' => AbwesenheitScheduleStatus::Applied,
        'applied_at' => now()->subHour(),
    ]);

    artisan('intranet-app-abwesenheit:backfill-mailbox-grants')
        ->assertSuccessful();

    expect(MailboxGrant::query()->where('user_id', $owner->id)->count())->toBe(1);
});

test('backfill ignores pending schedules', function (): void {
    $owner = User::factory()->create(['username' => 'pending.owner', 'active' => true]);
    $delegate = User::factory()->create(['username' => 'pending.delegate', 'active' => true]);

    AbwesenheitSchedule::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
        'payload' => [
            'email_vertreter' => $delegate->username,
            'email_delegate' => true,
            'call_forwarding' => false,
            'd3_forwarding' => false,
        ],
        'status' => AbwesenheitScheduleStatus::Pending,
    ]);

    artisan('intranet-app-abwesenheit:backfill-mailbox-grants')
        ->assertSuccessful();

    expect(MailboxGrant::query()->count())->toBe(0);
});
