<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>

<body>

    <table align="center" border="0" cellpadding="0" cellspacing="0" style="background:#efeff4; padding:0; margin:0;"
        width="100%">
        <tbody>
            <tr>
                <td></td>
                <td align="center" style="padding:0;border-collapse:collapse" valign="top" width="600">
                    <table align="center" border="0" cellpadding="0" cellspacing="0">
                        <tbody>
                            <tr>
                                <td align="left" colspan="2" style="padding:0" valign="top">
                                    <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tbody>
                                            <tr>
                                                <td style="text-align:left;padding:10px 0px 12px 30px" valign="bottom"
                                                    width="100%"><a href="{{ config('email_logo_redirection_url') }}"
                                                        style="text-decoration:none;outline:none"><img
                                                            src="{{ $mailData['logo'] }}"
                                                            title="{{ config('app.ame') }}" width="160" /> </a></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td align="left" colspan="2" style="padding:0;background:#efeff4" valign="top">
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tbody>
                                            <tr>
                                                <td align="left" style="background:#efeff4;padding:0" valign="top"
                                                    width="10"></td>
                                                <td align="left" style="background:#ffffff;padding:0" valign="top"
                                                    width="100%">
                                                    <table align="left" border="0" cellpadding="0" cellspacing="0"
                                                        width="100%">
                                                        <tbody>
                                                            <tr>
                                                                <td align="center"
                                                                    style="padding:0;font:normal 14px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;line-height:1.4em;background:#f9fafc"
                                                                    valign="top">
                                                                    <table align="left" border="0" cellpadding="0"
                                                                        cellspacing="0" width="100%">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td align="left" colspan="2"
                                                                                    style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px"
                                                                                    valign="top">
                                                                                    <div style="margin: 40px 40px;">
                                                                                    <img src="{{$mailData['ic_logo']}}" style="margin-left: 70%;">
                                                                                        <p
                                                                                            style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;"> 
                                                                                            Dear {{ $mailData['name'] }}
                                                                                           
                                                                                        </p>
                                                                                        
                                                                                        <div style="padding: 0px 10px;">

                                                                                        <p style="font-size: 0.85rem;letter-spacing: 0.3px;">
                                                                                            Thank you for choosing {{ config('app.name') }} as your Insurance Broker.
                                                                                        </p>
                                                                                        <p style="font-size: 0.85rem;letter-spacing: 0.3px;">
                                                                                                                                                                                               
                                                                                                @if (isset($mailData['isBreakin']) &&  $mailData['isBreakin'] == "Y")
                                                                                                     
                                                                                                    Based on the details shared, you choose {{$mailData['ic_name']}} to insure your vehicle  {{ $mailData['make_model'] }} with Vehicle Registration year {{ $mailData['vehicle_manf_year'] }} for inspection id is {{ $mailData['breakin_id'] }}, premium amount for the same is {{ $mailData['final_payable_amount'] }}<br>

                                                                                                @else
                                                                                                         Based on the details shared, you choose {{$mailData['ic_name']}} to insure your vehicle  {{ $mailData['make_model'] }} with Vehicle Registration year {{ $mailData['vehicle_manf_year'] }} premium amount for the same is {{ $mailData['final_payable_amount'] }}<br>


                                                                                                @endif
                                                                                                </p>

                                                                                            @if (isset($mailData['isOtpRequired']) &&  $mailData['isOtpRequired'] == "Y")
                                                                                                <p style="font-size: 0.9rem;">
                                                                                                    Your OTP for your journey on {{ config('app.name') }} <br>
                                                                                                <h3 style="text-align: center;">{{ $mailData['otp'] }}</h3>
                                                                                                </p>
                                                                                            @else
                                                                                                <p style="font-size: 0.9rem;">
                                                                                                {{ $mailData['otp'] }} is an OTP for proceeding ahead with this proposal. By sharing the OTP, you provide your consent to proceed with above proposal   {{ config('app.name') }} <br>
                                                                                                <!-- <h3 style="text-align: center;">{{ $mailData['otp'] }}</h3> -->
                                                                                                </p>
                                                                                            @endif
                                                                                           
                                                                                               
                                                                                            <p
                                                                                                style="margin-top: 30px;font-size: 0.85rem;font-weight: bold;letter-spacing: 0.3px;">
                                                                                                For any assistance,
                                                                                                please feel free to
                                                                                                connect with our
                                                                                                customer care at our
                                                                                                number
                                                                                                {{ config('constants.brokerConstant.tollfree_number') }}
                                                                                                or drop an e-mail at <a
                                                                                                    href="#">{{ config('constants.brokerConstant.support_email') }}</a>
                                                                                            </p>
                                                                                            <div
                                                                                                style="margin-top: 60px;">
                                                                                                <span>Yours
                                                                                                    Sincerely,</span><br><br>
                                                                                                <strong
                                                                                                    style="color: darkblue;">{{ config('app.name') }}
                                                                                                    Team</strong>
                                                                                            </div>
                                                                                        </div>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td
                                                                                    style="background:#2f445c; font-size:11px; color:#fff; text-align:center;">
                                                                                    <div
                                                                                        style="width:80%; padding:10px 0 10px; margin:0 auto;">
                                                                                        {{ config('constants.brokerConstant.code') }}
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                                <td align="left" style="background:#efeff4;padding:0" valign="top"
                                                    width="10"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>

</html>
