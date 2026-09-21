<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\HwkAdminLaravel\DTO\GetExchangePermissionOutputDTO;
use Hwkdo\HwkAdminLaravel\DTO\SetExchangePermissionDTO;
use Hwkdo\HwkAdminLaravel\HwkAdminService;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Models\MailboxGrant;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

test('grantMailboxDelegation adds FullAccess when delegate has no existing right', function (): void {
    $owner = User::factory()->create([
        'username' => 'owner.user',
        'active' => true,
    ]);
    $delegate = User::factory()->create([
        'username' => 'delegate.user',
        'active' => true,
    ]);

    $hwkAdmin = mock(HwkAdminService::class);
    $hwkAdmin->shouldReceive('getExchangePermission')
        ->once()
        ->with($owner->upn)
        ->andReturn(collect([
            new GetExchangePermissionOutputDTO(
                InheritanceType: 'All',
                User: 'NT AUTHORITY\\SELF',
                AccessRights: ['FullAccess'],
            ),
        ]));
    $hwkAdmin->shouldReceive('setExchangePermission')
        ->once()
        ->with(Mockery::on(function (SetExchangePermissionDTO $dto) use ($owner, $delegate): bool {
            return $dto->owner_upn === $owner->upn
                && $dto->delegate_upn === $delegate->upn
                && $dto->accessRights === 'FullAccess'
                && $dto->action === 'Add';
        }));
    app()->instance(HwkAdminService::class, $hwkAdmin);

    app(AbwesenheitService::class)->grantMailboxDelegation($owner, 'delegate.user');

    $grant = MailboxGrant::query()->where('user_id', $owner->id)->first();

    expect($grant)->not->toBeNull()
        ->and($grant->already_existed)->toBeFalse()
        ->and($grant->delegate_upn)->toBe($delegate->upn)
        ->and($grant->revoked_at)->toBeNull();
});

test('grantMailboxDelegation skips Add when FullAccess already exists', function (): void {
    $owner = User::factory()->create([
        'username' => 'owner.existing',
        'active' => true,
    ]);
    $delegate = User::factory()->create([
        'username' => 'delegate.existing',
        'active' => true,
    ]);

    $hwkAdmin = mock(HwkAdminService::class);
    $hwkAdmin->shouldReceive('getExchangePermission')
        ->once()
        ->andReturn(collect([
            new GetExchangePermissionOutputDTO(
                InheritanceType: 'All',
                User: $delegate->upn,
                AccessRights: ['FullAccess'],
            ),
        ]));
    $hwkAdmin->shouldReceive('setExchangePermission')->never();
    app()->instance(HwkAdminService::class, $hwkAdmin);

    app(AbwesenheitService::class)->grantMailboxDelegation($owner, 'delegate.existing');

    $grant = MailboxGrant::query()->where('user_id', $owner->id)->first();

    expect($grant)->not->toBeNull()
        ->and($grant->already_existed)->toBeTrue()
        ->and($grant->revoked_at)->toBeNull();
});

test('revokeMailboxGrants removes only grants that were added by absence', function (): void {
    $owner = User::factory()->create([
        'username' => 'owner.revoke',
        'active' => true,
    ]);

    MailboxGrant::query()->create([
        'user_id' => $owner->id,
        'owner_upn' => $owner->upn,
        'delegate_upn' => 'added.delegate@example.test',
        'access_rights' => 'FullAccess',
        'already_existed' => false,
        'granted_at' => now(),
    ]);
    MailboxGrant::query()->create([
        'user_id' => $owner->id,
        'owner_upn' => $owner->upn,
        'delegate_upn' => 'preexisting.delegate@example.test',
        'access_rights' => 'FullAccess',
        'already_existed' => true,
        'granted_at' => now(),
    ]);

    $hwkAdmin = mock(HwkAdminService::class);
    $hwkAdmin->shouldReceive('setExchangePermission')
        ->once()
        ->with(Mockery::on(function (SetExchangePermissionDTO $dto): bool {
            return $dto->delegate_upn === 'added.delegate@example.test'
                && $dto->action === 'Remove';
        }));
    $hwkAdmin->shouldReceive('resetExchangePermission')->never();
    $hwkAdmin->shouldReceive('getExchangePermission')->never();
    app()->instance(HwkAdminService::class, $hwkAdmin);

    app(AbwesenheitService::class)->revokeMailboxGrants($owner);

    expect(MailboxGrant::query()->active()->where('user_id', $owner->id)->count())->toBe(0)
        ->and(MailboxGrant::query()->where('user_id', $owner->id)->whereNotNull('revoked_at')->count())->toBe(2);
});

test('revokeMailboxGrants does not call exchange when only already_existed grants exist', function (): void {
    $owner = User::factory()->create([
        'username' => 'owner.keep',
        'active' => true,
    ]);

    MailboxGrant::query()->create([
        'user_id' => $owner->id,
        'owner_upn' => $owner->upn,
        'delegate_upn' => 'preexisting.keep@example.test',
        'access_rights' => 'FullAccess',
        'already_existed' => true,
        'granted_at' => now(),
    ]);

    $hwkAdmin = mock(HwkAdminService::class);
    $hwkAdmin->shouldReceive('setExchangePermission')->never();
    $hwkAdmin->shouldReceive('resetExchangePermission')->never();
    app()->instance(HwkAdminService::class, $hwkAdmin);

    app(AbwesenheitService::class)->revokeMailboxGrants($owner);

    $grant = MailboxGrant::query()->where('user_id', $owner->id)->first();

    expect($grant->revoked_at)->not->toBeNull();
});

test('revokeMailboxGrants is a noop without active grants', function (): void {
    $owner = User::factory()->create([
        'username' => 'owner.noop',
        'active' => true,
    ]);

    $hwkAdmin = mock(HwkAdminService::class);
    $hwkAdmin->shouldReceive('setExchangePermission')->never();
    $hwkAdmin->shouldReceive('getExchangePermission')->never();
    $hwkAdmin->shouldReceive('resetExchangePermission')->never();
    app()->instance(HwkAdminService::class, $hwkAdmin);

    app(AbwesenheitService::class)->revokeMailboxGrants($owner);

    expect(MailboxGrant::query()->count())->toBe(0);
});

test('grantMailboxDelegation stores schedule_id when provided', function (): void {
    $owner = User::factory()->create([
        'username' => 'owner.schedule',
        'active' => true,
    ]);
    User::factory()->create([
        'username' => 'delegate.schedule',
        'active' => true,
    ]);

    $schedule = AbwesenheitSchedule::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'starts_at' => now(),
        'ends_at' => now()->addDay(),
        'payload' => [],
        'status' => AbwesenheitScheduleStatus::Pending,
    ]);

    $hwkAdmin = mock(HwkAdminService::class);
    $hwkAdmin->shouldReceive('getExchangePermission')->once()->andReturn(new Collection);
    $hwkAdmin->shouldReceive('setExchangePermission')->once();
    app()->instance(HwkAdminService::class, $hwkAdmin);

    app(AbwesenheitService::class)->grantMailboxDelegation($owner, 'delegate.schedule', $schedule->id);

    expect(MailboxGrant::query()->where('user_id', $owner->id)->value('schedule_id'))->toBe($schedule->id);
});
