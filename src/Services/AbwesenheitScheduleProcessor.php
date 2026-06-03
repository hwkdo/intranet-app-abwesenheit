<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Services;

use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Throwable;

class AbwesenheitScheduleProcessor
{
    public function __construct(
        private readonly AbwesenheitService $abwesenheitService,
    ) {}

    public function processDueSchedules(): void
    {
        $this->processStarts();
        $this->processEnds();
    }

    private function processStarts(): void
    {
        AbwesenheitSchedule::query()
            ->pending()
            ->where('starts_at', '<=', now())
            ->with('user')
            ->each(function (AbwesenheitSchedule $schedule): void {
                $this->applySchedule($schedule);
            });
    }

    private function processEnds(): void
    {
        AbwesenheitSchedule::query()
            ->applied()
            ->where('ends_at', '<=', now())
            ->with('user')
            ->each(function (AbwesenheitSchedule $schedule): void {
                $this->completeSchedule($schedule);
            });
    }

    public function applySchedule(AbwesenheitSchedule $schedule): void
    {
        if ($schedule->status !== AbwesenheitScheduleStatus::Pending) {
            return;
        }

        try {
            $this->abwesenheitService->apply($schedule->user, $schedule->storeData());
            $schedule->update([
                'status' => AbwesenheitScheduleStatus::Applied,
                'applied_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $schedule->update([
                'status' => AbwesenheitScheduleStatus::Failed,
                'last_error' => $exception->getMessage(),
            ]);
        }
    }

    public function completeSchedule(AbwesenheitSchedule $schedule): void
    {
        if ($schedule->status !== AbwesenheitScheduleStatus::Applied) {
            return;
        }

        try {
            if ($this->abwesenheitService->isActive($schedule->user)) {
                $this->abwesenheitService->destroy($schedule->user);
            }

            $schedule->update([
                'status' => AbwesenheitScheduleStatus::Completed,
                'completed_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $schedule->update([
                'status' => AbwesenheitScheduleStatus::Failed,
                'last_error' => $exception->getMessage(),
            ]);
        }
    }

    public function markEndedEarlyForUser(int $userId): void
    {
        AbwesenheitSchedule::query()
            ->applied()
            ->where('user_id', $userId)
            ->update([
                'status' => AbwesenheitScheduleStatus::EndedEarly,
                'ended_early_at' => now(),
                'completed_at' => now(),
            ]);
    }

    public static function userHasOpenSchedule(int $userId): bool
    {
        return AbwesenheitSchedule::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                AbwesenheitScheduleStatus::Pending,
                AbwesenheitScheduleStatus::Applied,
            ])
            ->exists();
    }

    public static function userHasPendingSchedule(int $userId): bool
    {
        return AbwesenheitSchedule::query()
            ->pending()
            ->where('user_id', $userId)
            ->exists();
    }
}
