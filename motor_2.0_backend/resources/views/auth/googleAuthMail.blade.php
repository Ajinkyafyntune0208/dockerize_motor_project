<!-- resources/views/emails/otp.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google 2FA Verification: QR Code for Your Account</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #3498db;
        }

        p {
            line-height: 1.6;
        }

        .otp-code {
            font-size: 24px;
            font-weight: bold;
            color: #ff0000;
        }
    </style>
</head>
<body>
    <div class="container">
        <p>
            Please follow the instructions below to set up 2FA on <b>{{config('app.name')}}</b>{{in_array(config('app.env'), ['test', 'production']) ? '' : ' ('.config('app.env').')'}} account using an authenticator app:
        </p>
        <h3>Steps to Enable 2FA : </h3>
        <ul>
            <li>Open your preferred authenticator app (e.g., Google Authenticator).</li>
            <li>Select the option to add a new account.</li>
            <li>Scan the QR code provided in the attachment.</li>
            <li>After scanning, your app will display a 6-digit code that changes every few seconds. Use this code for future login attempts.</li>
        </ul>
    </div>
</body>
</html>
