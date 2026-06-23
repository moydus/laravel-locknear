<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment required — LockNear</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, sans-serif; background: #1a1208; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { background: #2a1f0f; border-radius: 24px; padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; }
    .icon { font-size: 64px; margin-bottom: 24px; }
    h1 { font-size: 28px; font-weight: 700; margin-bottom: 12px; }
    p { color: rgba(255,255,255,0.75); line-height: 1.6; margin-bottom: 8px; }
    a { color: #fbbf24; font-weight: 700; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">💳</div>
    <h1>Payment required</h1>
    <p>{{ $message }}</p>
    <p style="margin-top: 24px;">
      <a href="{{ \App\Support\LockNearUrls::providerApp() }}/subscription">Open subscription settings →</a>
    </p>
  </div>
</body>
</html>
