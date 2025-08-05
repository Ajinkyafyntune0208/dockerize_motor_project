<!-- resources/views/emails/rc_report.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Your Report</title>
</head>
<body>
    <h1>Your Policy Report is Ready!</h1>
    <p>Click the link below to download your report:</p>
    <a href="{{ $url }}" target="_blank">Download Report</a>
</body>
</html>