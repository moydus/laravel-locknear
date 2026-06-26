<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Provider presence (online / away / offline)
  |--------------------------------------------------------------------------
  |
  | online_minutes — heartbeat within this window = "online"
  | away_minutes   — heartbeat within this window = "away" (still is_online)
  |                  after this, markStaleOffline() sets is_online=false
  |
  */
  'presence' => [
    'online_minutes' => (int) env('LOCKNEAR_PRESENCE_ONLINE_MINUTES', 1),
    'away_minutes' => (int) env('LOCKNEAR_PRESENCE_AWAY_MINUTES', 2),
    'heartbeat_seconds' => (int) env('LOCKNEAR_PRESENCE_HEARTBEAT_SECONDS', 30),
  ],

  /*
  |--------------------------------------------------------------------------
  | Dispatch
  |--------------------------------------------------------------------------
  */
  'dispatch' => [
    'accept_token_minutes' => (int) env('LOCKNEAR_DISPATCH_ACCEPT_MINUTES', 30),
    'require_subscription' => env('LOCKNEAR_DISPATCH_REQUIRE_SUBSCRIPTION', true),
    'strategy' => env('LOCKNEAR_DISPATCH_STRATEGY', 'hybrid'),
    'max_parallel_offers' => (int) env('LOCKNEAR_DISPATCH_MAX_PARALLEL_OFFERS', 3),
    'offer_ttl_seconds' => (int) env('LOCKNEAR_DISPATCH_OFFER_TTL_SECONDS', 60),
    'weights' => [
      'distance' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_DISTANCE', 0.35),
      'eta' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_ETA', 0.35),
      'quality' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_QUALITY', 0.20),
      'acceptance' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_ACCEPTANCE', 0.10),
      'cancellation' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_CANCELLATION', 0.10),
      'availability' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_AVAILABILITY', 0.10),
    ],
  ],

  'pricing' => [
    'default_currency' => env('LOCKNEAR_PRICING_CURRENCY', 'usd'),
    'default_commission_rate' => (float) env('LOCKNEAR_COMMISSION_RATE', 0.15),
    'surge_enabled' => env('LOCKNEAR_SURGE_ENABLED', false),
  ],

  /*
  |--------------------------------------------------------------------------
  | Provider app (app.locknear.com)
  |--------------------------------------------------------------------------
  */
  'app_url' => env('APP_LOCKNEAR_URL', 'http://localhost:3000'),

];
