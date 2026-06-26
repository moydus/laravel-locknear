<?php

namespace App\Enums;

enum PaymentState: string
{
    case Created = 'created';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $state): bool
    {
        return in_array($state, match ($this) {
            self::Created => [self::Authorized, self::Failed, self::Cancelled],
            self::Authorized => [self::Captured, self::Cancelled, self::Failed],
            self::Captured => [self::Refunded],
            self::Refunded, self::Failed, self::Cancelled => [],
        }, true);
    }
}
