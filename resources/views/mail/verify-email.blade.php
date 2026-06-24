<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your LockNear account</title>
</head>
<body style="margin:0;padding:0;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111111;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff;padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;">
                    <tr>
                        <td style="padding-bottom:24px;">
                            <img src="https://cdn.locknear.com/logo.png" alt="LockNear" style="height:36px;width:auto;display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:8px;font-size:20px;font-weight:600;line-height:28px;color:#111111;">
                            Verify your email to get started
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:24px;font-size:14px;line-height:22px;color:#71717a;">
                            Click the button below to verify <strong style="color:#111111;">{{ $user->email }}</strong> and finish setting up your LockNear provider account. This link expires in 1 hour.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:24px;">
                            <a href="{{ $verifyUrl }}" style="display:inline-block;padding:12px 20px;border-radius:10px;background:#000000;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;line-height:20px;">
                                Verify email
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:24px;font-size:13px;line-height:20px;color:#71717a;">
                            If the button does not work, copy and paste this link into your browser:<br>
                            <a href="{{ $verifyUrl }}" style="color:#111111;word-break:break-all;">{{ $verifyUrl }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:16px;border-top:1px solid #e4e4e7;font-size:12px;line-height:18px;color:#a1a1aa;">
                            If you did not create a LockNear account, you can ignore this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
