<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Offline Vahan Data Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
    <h1> Click the link below to download your report </h1>
    <a href="{{ $url }}">Download Link</a>
    <p>The link will expire in {{$expirytime ?? null}} minutes</p>
</body>

</html>