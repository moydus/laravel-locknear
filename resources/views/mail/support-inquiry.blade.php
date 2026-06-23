<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Support inquiry</title>
</head>
<body style="margin:0;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#111;">
    <h1 style="font-size:20px;margin:0 0 16px;">New support message</h1>
    <p style="margin:0 0 8px;"><strong>From:</strong> {{ $inquiry['name'] }} &lt;{{ $inquiry['email'] }}&gt;</p>
    @if (!empty($inquiry['phone']))
        <p style="margin:0 0 8px;"><strong>Phone:</strong> {{ $inquiry['phone'] }}</p>
    @endif
    <p style="margin:0 0 16px;"><strong>Topic:</strong> {{ str($inquiry['topic'])->replace('-', ' ')->title() }}</p>
    <div style="padding:16px;border-radius:12px;background:#f5f5f5;white-space:pre-line;">{{ $inquiry['message'] }}</div>
</body>
</html>
