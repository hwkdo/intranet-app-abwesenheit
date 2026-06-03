<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Enums;

enum AbwesenheitScheduleStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case EndedEarly = 'ended_early';
    case Failed = 'failed';
}
