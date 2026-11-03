<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0; padding:0; background:#F7F7FA; font-family:Tahoma, Arial, sans-serif; color:#22223B;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F7FA;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="width:100%; max-width:520px; background:#FFFFFF; border:1px solid #E6E6EE; border-radius:12px;">
                <tr>
                    <td style="padding:28px 32px 4px; font-size:24px; font-weight:700; color:#5B5BD6; letter-spacing:-0.5px;">
                        orbit
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 32px; font-size:16px; line-height:2; color:#22223B;">
                        {{ $text }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 32px 32px;">
                        <a href="{{ $url }}" style="display:inline-block; background:#5B5BD6; color:#FFFFFF; text-decoration:none; font-size:15px; font-weight:700; padding:12px 24px; border-radius:8px;">
                            مشاهده در {{ $appName }}
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 32px; border-top:1px solid #E6E6EE; font-size:12px; line-height:1.9; color:#6B6B85;">
                        این ایمیل طبق تنظیمات اعلان‌های تو ارسال شده است.
                        <a href="{{ $settingsUrl }}" style="color:#5B5BD6;">تغییر تنظیمات</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
