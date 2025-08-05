<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ config('app.name') }}</title>
</head>

<body>
    <h3>{{ $mailData['greeting'] }},</h3>
    <br>
    <p>
        {{ $mailData['line1'] }}
    </p>
    <br>
    <a href="{{ $mailData['action'][1] ?? '' }}" download="true">{{ $mailData['action'][0] ?? '' }}</a>
    <p>
        For any further assistance, please call us on {{ config('constants.brokerConstant.tollfree_number') }} or write
        to us at <a
            href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
    </p>
    <br>
    <br>
    <span>Yours Sincerely,</span> <br>
    <br>
    <strong style="color: darkblue;">{{ config('app.name') }}
         Team
    </strong>
</body>

</html>
