<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;border:1px solid #e5e5e5;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 28px 8px;">
                            <img src="https://cdn.locknear.com/logo.png" alt="LockNear" style="height:32px;width:auto;display:block;margin-bottom:12px;">
                            <h1 style="margin:0;font-size:24px;line-height:1.3;color:#111111;">{{ $headline }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 28px 24px;">
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#404040;white-space:pre-line;">{{ $body }}</p>
                            @if ($actionUrl && $actionLabel)
                                <p style="margin:24px 0 0;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#111111;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:12px 20px;border-radius:12px;">{{ $actionLabel }}</a>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 24px;border-top:1px solid #f0f0f0;">
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#737373;">
                                Need help? Reply to this email or call (833) 556-2532.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
