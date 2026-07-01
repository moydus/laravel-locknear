<?php

return [
    'enabled' => (bool) env('WORK_ORDER_V2_ENABLED', true),
    'dispatch_fee_cents' => (int) env('WORK_ORDER_DISPATCH_FEE_CENTS', 3900),
    'minimum_service_authorization_cents' => (int) env('WORK_ORDER_MIN_SERVICE_AUTHORIZATION_CENTS', 9500),
    'evidence_retention_days' => (int) env('WORK_ORDER_EVIDENCE_RETENTION_DAYS', 30),
    'provider_no_show_minutes' => (int) env('WORK_ORDER_PROVIDER_NO_SHOW_MINUTES', 60),
];
