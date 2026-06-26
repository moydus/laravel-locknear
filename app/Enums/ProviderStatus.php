<?php

namespace App\Enums;

enum ProviderStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
