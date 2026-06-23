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
  ],

  /*
  |--------------------------------------------------------------------------
  | Provider app (app.locknear.com)
  |--------------------------------------------------------------------------
  */
  'app_url' => env('APP_LOCKNEAR_URL', 'http://localhost:3000'),

];
