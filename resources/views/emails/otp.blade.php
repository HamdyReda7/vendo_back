<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password Reset Code</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f7; padding: 20px;">
    <div style="max-width: 500px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2 style="color: #333333; margin-top: 0;">Password Reset Code</h2>
        <p style="color: #666666; font-size: 16px;">Use the following 6-digit verification code to reset your password. This code will expire in 10 minutes.</p>
        <div style="font-size: 32px; font-weight: bold; letter-spacing: 6px; text-align: center; color: #2563eb; background: #eff6ff; padding: 15px; border-radius: 6px; margin: 25px 0;">
            {{ $otp }}
        </div>
        <p style="color: #999999; font-size: 14px;">If you did not request a password reset, please ignore this email.</p>
    </div>
</body>
</html>
