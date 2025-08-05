<!-- resources/views/emails/otp.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Email</title>
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
    <h1>Your Two Factor Authentication Details</h1>
    <p>Scan this Qrcode using any authenticater application from your mobile.</p>
    <img style="width: 200px; margin-top: 10px"
                                    src="data:image/png;base64,{!! $qrcode !!}" alt="" />
    <p>Security Key: {{ $secret }}</p>
    </div>
</body>
</html>
