<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your LockNear account</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111111;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f5;padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 28px 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding-right:12px;vertical-align:middle;">
                                        <img src="{{ $logoUrl }}" alt="LockNear" width="32" height="34" style="display:block;width:32px;height:34px;">
                                    </td>
                                    <td style="vertical-align:middle;font-size:18px;font-weight:700;line-height:24px;color:#111111;">
                                        LockNear
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px 8px;font-size:22px;font-weight:700;line-height:30px;color:#111111;">
                            Verify your email
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 20px;font-size:15px;line-height:24px;color:#52525b;">
                            Hi {{ $user->name }},<br><br>
                            Thanks for signing up as a LockNear provider. Confirm
                            <strong style="color:#111111;">{{ $user->email }}</strong>
                            to finish creating your account and continue setup.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 24px;">
                            <a href="{{ $verifyUrl }}" style="display:inline-block;padding:13px 22px;border-radius:10px;background:#111111;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;line-height:20px;">
                                Verify email address
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 24px;font-size:13px;line-height:20px;color:#71717a;">
                            This link expires in 1 hour. If the button does not work, copy and paste this URL into your browser:<br>
                            <a href="{{ $verifyUrl }}" style="color:#111111;word-break:break-all;">{{ $verifyUrl }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px;border-top:1px solid #e4e4e7;font-size:12px;line-height:18px;color:#a1a1aa;">
                            If you did not create a LockNear provider account, you can ignore this email.<br>
                            <a href="{{ $marketingUrl }}" style="color:#71717a;text-decoration:underline;">locknear.com</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
