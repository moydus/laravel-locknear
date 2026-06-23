<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Accepted — LockNear</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, sans-serif; background: #0f2318; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { background: #1c2706; border-radius: 24px; padding: 48px 40px; max-width: 480px; width: 100%; text-align: center; }
    .icon { font-size: 64px; margin-bottom: 24px; }
    h1 { font-size: 28px; font-weight: 700; margin-bottom: 12px; }
    p { color: rgba(255,255,255,0.7); line-height: 1.6; margin-bottom: 8px; }
    .detail { background: rgba(255,255,255,0.08); border-radius: 12px; padding: 16px 20px; margin-top: 24px; text-align: left; }
    .detail strong { color: #86efac; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">✅</div>
    <h1>Job Accepted!</h1>
    <p>You've claimed this lead. The customer has been notified that you're on the way.</p>
    @isset($assignment)
      <p style="margin-top: 12px; color: #86efac; font-weight: 600;">Lead fee: ${{ number_format((float) $assignment->lead_cost, 2) }}</p>
    @endisset
    <div class="detail">
      <p><strong>Service:</strong> {{ ucwords(str_replace('-', ' ', $lead->service_type)) }}</p>
      <p><strong>ZIP:</strong> {{ $lead->zip }}</p>
      <p><strong>Customer Phone:</strong> {{ $lead->phone }}</p>
    </div>
    <p style="margin-top: 24px;">
      <a href="{{ \App\Support\LockNearUrls::providerLead($lead->id) }}" style="color: #86efac; font-weight: 700; text-decoration: underline;">
        Open job in LockNear app →
      </a>
    </p>
  </div>
</body>
</html>
