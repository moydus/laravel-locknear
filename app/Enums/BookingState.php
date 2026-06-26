<?php

namespace App\Enums;

enum BookingState: string
{
    case Pending = 'pending';
    case Searching = 'searching';
    case Matched = 'matched';
    case Accepted = 'accepted';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case Working = 'working';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function canTransitionTo(self $state): bool
    {
        return in_array($state, match ($this) {
            self::Pending => [self::Searching, self::Cancelled],
            self::Searching => [self::Matched, self::Cancelled],
            self::Matched => [self::Accepted, self::Searching, self::Cancelled],
            self::Accepted => [self::EnRoute, self::Cancelled],
            self::EnRoute => [self::Arrived, self::Cancelled],
            self::Arrived => [self::Working, self::Completed, self::Cancelled],
            self::Working => [self::Completed, self::Cancelled],
            self::Completed => [self::Refunded],
            self::Cancelled => [self::Refunded],
            self::Refunded => [],
        }, true);
    }
}
