<?php

namespace App\Support;

use App\Models\Lead;

class LockNearUrls
{
    public static function frontend(): string
    {
        return rtrim((string) config('services.frontend_url', 'https://locknear.com'), '/');
    }

    public static function api(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function providerApp(): string
    {
        return rtrim((string) config('services.provider_url', 'https://app.locknear.com'), '/');
    }

    public static function customerTrack(Lead $lead): string
    {
        return self::frontend() . '/track/' . $lead->customer_token;
    }

    public static function dispatchAccept(string $token): string
    {
        return self::api() . '/api/dispatch/accept/' . $token;
    }

    public static function dispatchReject(string $token): string
    {
        return self::api() . '/api/dispatch/reject/' . $token;
    }

    public static function providerLead(int $leadId): string
    {
        return self::providerApp() . '/leads/' . $leadId;
    }
}
