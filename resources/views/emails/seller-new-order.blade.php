<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
</head>
<body style="margin: 0; padding: 20px; background-color: #f0f0f0; font-family: Arial, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
    <tr>
        <td>
            <div style="background-color: #1a1a2e; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
                <h1 style="color: white; margin: 0; font-size: 22px;">{{ $title }}</h1>
            </div>

            <div style="background-color: #ffffff; padding: 30px; border-left: 1px solid #e8e8e8; border-right: 1px solid #e8e8e8; color: #333333; line-height: 1.7;">
                {!! $body !!}
            </div>

            <div style="background-color: #f7f7f7; padding: 20px; text-align: center; font-size: 13px; color: #888888; border-radius: 0 0 8px 8px; border: 1px solid #e8e8e8; border-top: none;">
                <p style="margin: 0;">© {{ date('Y') }} {{ $storeName }}</p>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
