<?php

namespace App\Domain\Shared\Enums;

enum TenderStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case PartiallyAwarded = 'partially_awarded';
    case Awarded = 'awarded';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
