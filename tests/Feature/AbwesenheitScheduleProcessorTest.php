<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitStoreData;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitScheduleProcessor;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

test('processor applies pending schedule when start is due', function (): void {
    Carbon::setTestNow('2026-06-10 08:00:00');

    $user = User::factory()->create(['active' => true]);
    $creator = User::factory()->create(['active' => true]);

    $schedule = AbwesenheitSchedule::query()->create([
        'user_id' => $user->id,
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addWeek(),
        'payload' => (new AbwesenheitStoreData(
            email_vertreter: 'v.user',
            email_delegate: false,
            call_forwarding: false,
            d3_forwarding: false,
        ))->toArray(),
        'status' => AbwesenheitScheduleStatus::Pending,
    ]);

    $abwesenheit = mock(AbwesenheitService::class);
    $abwesenheit->shouldReceive('apply')->once();
    $abwesenheit->shouldReceive('isActive')->andReturn(false);

    $processor = new AbwesenheitScheduleProcessor($abwesenheit);
    $processor->processDueSchedules();

    $schedule->refresh();
    expect($schedule->status)->toBe(AbwesenheitScheduleStatus::Applied)
        ->and($schedule->applied_at)->not->toBeNull();

    Carbon::setTestNow();
});

test('processor completes schedule without destroy when already inactive', function (): void {
    Carbon::setTestNow('2026-06-20 08:00:00');

    $user = User::factory()->create(['active' => true]);
    $creator = User::factory()->create(['active' => true]);

    $schedule = AbwesenheitSchedule::query()->create([
        'user_id' => $user->id,
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subWeeks(2),
        'ends_at' => now()->subMinute(),
        'payload' => [],
        'status' => AbwesenheitScheduleStatus::Applied,
        'applied_at' => now()->subWeeks(2),
    ]);

    $abwesenheit = mock(AbwesenheitService::class);
    $abwesenheit->shouldReceive('isActive')->once()->andReturn(false);
    $abwesenheit->shouldReceive('destroy')->never();

    $processor = new AbwesenheitScheduleProcessor($abwesenheit);
    $processor->completeSchedule($schedule);

    $schedule->refresh();
    expect($schedule->status)->toBe(AbwesenheitScheduleStatus::Completed);

    Carbon::setTestNow();
});

test('markEndedEarlyForUser closes applied schedules', function (): void {
    $user = User::factory()->create(['active' => true]);
    $creator = User::factory()->create(['active' => true]);

    AbwesenheitSchedule::query()->create([
        'user_id' => $user->id,
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addWeek(),
        'payload' => [],
        'status' => AbwesenheitScheduleStatus::Applied,
    ]);

    app(AbwesenheitScheduleProcessor::class)->markEndedEarlyForUser($user->id);

    expect(AbwesenheitSchedule::query()->where('user_id', $user->id)->first()->status)
        ->toBe(AbwesenheitScheduleStatus::EndedEarly);
});
