<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Models;

use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitStoreData;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbwesenheitSchedule extends Model
{
    protected $table = 'intranet_app_abwesenheit_schedules';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'applied_at' => 'datetime',
            'completed_at' => 'datetime',
            'ended_early_at' => 'datetime',
            'payload' => 'array',
            'status' => AbwesenheitScheduleStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AbwesenheitModels::user());
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AbwesenheitModels::user(), 'created_by_user_id');
    }

    public function storeData(): AbwesenheitStoreData
    {
        return AbwesenheitStoreData::fromArray($this->payload ?? []);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AbwesenheitScheduleStatus::Pending);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', AbwesenheitScheduleStatus::Applied);
    }
}
