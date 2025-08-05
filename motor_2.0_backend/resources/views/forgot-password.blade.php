<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>

<body>
    <h1>Reset Your Password</h1>
    <p>We have received a request to reset the password for your account. If you did not request this, please ignore
        this email.</p>
    <p>
        To reset your password, please click on the link below:
    </p>
    <a href="{{ $url }}">Reset Password</a>
    <p>The link will expire in {{$extras['expiry'] ?? null}} minutes. If you are unable to reset your password within
        this period, please request a new reset link by following the same steps.</p>
</body>

</html>