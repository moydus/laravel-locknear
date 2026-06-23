<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Lead charge receipt</title>
</head>
<body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#111;">
    <h1 style="font-size:20px;margin:0 0 16px;">Lead fee charged</h1>
    <p style="margin:0 0 8px;"><strong>{{ $company->name }}</strong> accepted a LockNear lead.</p>
    <p style="margin:0 0 8px;"><strong>Service:</strong> {{ str($lead->service_type)->replace('-', ' ')->title() }}</p>
    <p style="margin:0 0 8px;"><strong>Location:</strong> {{ $lead->city ? "{$lead->city}, {$lead->state}" : "ZIP {$lead->zip}" }}</p>
    <p style="margin:0 0 16px;"><strong>Amount:</strong> ${{ number_format($amount, 2) }}</p>
    @if ($chargeId)
        <p style="margin:0;font-size:12px;color:#666;">Stripe reference: {{ $chargeId }}</p>
    @endif
    <p style="margin:16px 0 0;font-size:14px;">Open the provider app to start navigation and update job status.</p>
</body>
</html>
