<!DOCTYPE html>
<html>
<head>
    <title>OTP Code</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <h2 style="color: #4a5568;">Authentication Service</h2>
        <p>Hello,</p>
        <p>Your One-Time Password (OTP) is:</p>
        
        <div style="background-color: #f7fafc; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #2d3748;">{{ $otp }}</span>
        </div>
        
        <p>This code is valid for <strong>5 minutes</strong>.</p>
        <p>If you did not request this code, please ignore this email.</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        <p style="font-size: 12px; color: #718096;">This is an automated message, please do not reply.</p>
    </div>
</body>
</html>
