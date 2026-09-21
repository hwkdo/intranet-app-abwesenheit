<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Models;

use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxGrant extends Model
{
    protected $table = 'intranet_app_abwesenheit_mailbox_grants';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'already_existed' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AbwesenheitModels::user());
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AbwesenheitSchedule::class, 'schedule_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
};
