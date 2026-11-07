<?php

namespace App\Domain\Shared\Enums;

enum WorkOrderStatus: string
{
    case Issued = 'issued';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Disputed = 'disputed';
}
