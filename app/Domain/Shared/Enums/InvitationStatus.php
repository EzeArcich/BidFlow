<?php

namespace App\Domain\Shared\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Responded = 'responded';
    case Declined = 'declined';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
