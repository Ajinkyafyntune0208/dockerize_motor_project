<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email</title>
</head>
<body>
    <p>
    <p>Hello {{$name}},</p>
    <p>This is to inform you that your password for the  &nbsp;{{config('constants.brokerName')}} &nbsp; account associated with {{$email}} is due for renewal in the next 7 days. To ensure the security of your account, we are reaching out to prompt you about the upcoming password expiry.</p>
    <p>To maintain the security of your account, please be informed that you won't be able to reuse your last three passwords.</p>
    <p>To reset your password, &nbsp;<a href="{{ route('reset-password')}}?token={{$token}}">click here</a></p>
    </p>
</body>
</html>