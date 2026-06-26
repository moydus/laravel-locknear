<?php

namespace App\Enums;

enum CompanyLifecycleStatus: string
{
    case Imported = 'imported';
    case Unclaimed = 'unclaimed';
    case Invited = 'invited';
    case ClaimPending = 'claim_pending';
    case Verified = 'verified';
    case Active = 'active';
    case Suspended = 'suspended';
}
