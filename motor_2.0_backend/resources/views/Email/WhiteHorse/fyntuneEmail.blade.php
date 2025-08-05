<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body>
    <p>Dear {{$mailData['name']}},<br>
        We aim to share you details about the garage you've selected for {{$mailData['company']}} for future vehicle
        servicing or repairs. The details are as follows <br>
        Garage Name: {{$mailData['garageName']}}<br>
        Location: {{$mailData['garageAddress']}},{{$mailData['garagePincode']}}.<br>
        Contact Number: {{$mailData['garageMobileNo']}}<br><br>
        For any automotive assistance, feel free to reach out directly to the specified garage. Should you have inquiries or
        require additional support, please do not hesitate to contact {{$mailData['company']}} at {{$mailData['companycontact_no']}} or
        contact {{$mailData['brokerName']}} at {{$mailData['tollFreeNumber']}}<br>
    
        Best regards,<br>
        The {{$mailData['brokerName']}} Team</p>
</body>
</html>