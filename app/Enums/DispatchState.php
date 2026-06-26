<?php

namespace App\Enums;

enum DispatchState: string
{
    case Searching = 'searching';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Redispatched = 'redispatched';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $state): bool
    {
        return in_array($state, match ($this) {
            self::Searching => [self::Offered, self::Expired, self::Cancelled],
            self::Offered => [self::Accepted, self::Expired, self::Redispatched, self::Cancelled],
            self::Accepted => [self::Completed, self::Redispatched, self::Cancelled],
            self::Expired => [self::Redispatched, self::Cancelled],
            self::Redispatched => [self::Offered, self::Expired, self::Cancelled],
            self::Completed, self::Cancelled => [],
        }, true);
    }
}
