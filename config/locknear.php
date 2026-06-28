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
    'require_subscription' => env('LOCKNEAR_DISPATCH_REQUIRE_SUBSCRIPTION', false),
    'lead_billing_enabled' => env('LOCKNEAR_LEAD_BILLING_ENABLED', false),
    'strategy' => env('LOCKNEAR_DISPATCH_STRATEGY', 'hybrid'),
    'max_parallel_offers' => (int) env('LOCKNEAR_DISPATCH_MAX_PARALLEL_OFFERS', 3),
    'offer_ttl_seconds' => (int) env('LOCKNEAR_DISPATCH_OFFER_TTL_SECONDS', 60),
    'weights' => [
      'distance' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_DISTANCE', 0.25),
      'eta' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_ETA', 0.30),
      'quality' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_QUALITY', 0.20),
      'acceptance' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_ACCEPTANCE', 0.10),
      'cancellation' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_CANCELLATION', 0.05),
      'availability' => (float) env('LOCKNEAR_DISPATCH_WEIGHT_AVAILABILITY', 0.10),
    ],
  ],

  'pricing' => [
    'default_currency' => env('LOCKNEAR_PRICING_CURRENCY', 'usd'),
    'default_commission_rate' => (float) env('LOCKNEAR_COMMISSION_RATE', 0.20),
    'surge_enabled' => env('LOCKNEAR_SURGE_ENABLED', false),
    'provider' => env('LOCKNEAR_PRICING_PROVIDER', 'rule_based'),
  ],

  'features' => [
    'escrow' => env('LOCKNEAR_FEATURE_ESCROW', false),
    'stripe_connect' => env('LOCKNEAR_FEATURE_STRIPE_CONNECT', false),
    'multi_dispatch' => env('LOCKNEAR_FEATURE_MULTI_DISPATCH', true),
    'google_eta' => env('LOCKNEAR_FEATURE_GOOGLE_ETA', false),
    'ai_pricing' => env('LOCKNEAR_FEATURE_AI_PRICING', false),
  ],

  'outreach' => [
    'ghost_dispatch_enabled' => env('LOCKNEAR_GHOST_DISPATCH_ENABLED', true),
    'ghost_dispatch_limit' => (int) env('LOCKNEAR_GHOST_DISPATCH_LIMIT', 20),
  ],

  /*
  |--------------------------------------------------------------------------
  | Provider app (app.locknear.com)
  |--------------------------------------------------------------------------
  */
  'app_url' => env('APP_LOCKNEAR_URL', 'http://localhost:3000'),

  'marketing_url' => env('LOCKNEAR_MARKETING_URL', 'https://locknear.com'),

];
