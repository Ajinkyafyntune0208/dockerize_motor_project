<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Compare Vehicle Insurance Policy</title>
</head>

<body>
    <table align="center" border="0" cellpadding="0" cellspacing="0" style="background:#efeff4; padding:0; margin:0;" width="100%">
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
                                                <!-- https://www.comparepolicy.com/cp/welcome-emails/images/cp-logo.png -->
                                                <td style="text-align:left;padding:10px 0px 12px 30px" valign="bottom" width="100%"><a href="{{ config('email_logo_redirection_url') }}" style="text-decoration:none;outline:none"><img src="{{ $mailData['logo'] }}" title="{{ config('app.name') }}" width="160" /> </a></td>
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
                                                <td align="left" style="background:#efeff4;padding:0" valign="top" width="10"></td>
                                                <td align="left" style="background:#ffffff;padding:0" valign="top" width="100%">
                                                    <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
                                                        <tbody>
                                                            <tr>
                                                                <td align="center" style="padding:0;font:normal 14px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;line-height:1.4em;background:#f9fafc" valign="top">
                                                                    <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
                                                                        <tbody>
                                                                            <tr>
                                                                                <td align="left" colspan="2" style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px" valign="top">
                                                                                    <div style="margin: 20px 40px; letter-spacing: 0.3px;">
                                                                                        <!-- <p style="margin-top:10px;color: rgb(218,21,76);font-weight: bold;font-size: 20px;">Dear Username</p> -->
                                                                                        <p style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;">Dear {{ $mailData['name'] }}</p>
                                                                                        <!-- <div style="padding: 0px 10px;"> -->
                                                                                        <!-- <p style="margin-top: 2.5rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">As per the details shared by you for purchasing Car Insurance on our website, following are the quotes from different insurers.</p> -->
                                                                                        <div style="padding: 10px 5px;letter-spacing: 0;font-size: 0.8rem;color: #222222;font-family: sans-serif;">As per the details shared by you for purchasing {{ $mailData['section'] }} Insurance on our website, following are the quotes from different insurers.</div>
                                                                                        <table style="width: 100%;">
                                                                                            <thead style="background: #0f4373;color: #fff;font-size: 0.7rem;">
                                                                                                <tr>
                                                                                                    <th style="padding-left: 15px;">Product Name</th>
                                                                                                    <th style="text-align: center;">Premium</th>
                                                                                                    <th style="text-align: center;">Action</th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody>
                                                                                                @foreach ($mailData['quotes'] as $quote)
                                                                                                <tr style="background: #f0fafe;color: #012870;">
                                                                                                    <td style="display: flex;">
                                                                                                        <img src="{{ $quote['logo'] }}" alt="" width="100px" height="60px">
                                                                                                        <p style="margin: 0;font-size: 0.7rem;font-weight: bold;align-self: center;padding-left: 15px;">
                                                                                                            {{ $quote['name'] }}
                                                                                                            <br>IDV: Rs.{{ $quote['idv'] }}
                                                                                                            @if(isset($quote['applicableAddonsList']) && !empty($quote['applicableAddonsList']))
                                                                                                                <br><span style="display:block;font-size: 0.65rem;font-weight: normal;letter-spacing: 0em;line-height: 0.95rem;padding: 4px 0px;">Included Addons: {{ $quote['applicableAddonsList'] }}</span>
                                                                                                            @endif
                                                                                                        </p>
                                                                                                    </td>
                                                                                                    @if(isset($mailData['gstSelected']) && $mailData['gstSelected'] === "Y")
                                                                                                    <td style="text-align: center;">Rs. {{ $quote['finalPremium'] }}</td>
                                                                                                    @else
                                                                                                    <td style="text-align: center;">Rs. {{ $quote['premium'] }}</td>
                                                                                                    @endif
                                                                                                    <td style="text-align: center;">
                                                                                                        <a href="{{ $quote['action'] }}" style="background: #0000ffb0;color: #fff;border: none;border-radius: 4px;padding: 8px;">Proceed</a>
                                                                                                    </td>
                                                                                                </tr>
                                                                                                @endforeach
                                                                                            </tbody>
                                                                                        </table>
                                                                                        @if(isset($mailData['gstSelected']) && $mailData['gstSelected'] === "Y")
                                                                                        <p style="font-size: 0.78rem;text-align: center;color: red;font-size: sans-serif;">*Above mentioned quotes are inclusive of GST</p>
                                                                                        @else
                                                                                        <p style="font-size: 0.78rem;text-align: center;color: red;font-size: sans-serif;">*Above mentioned quotes are exclusive of GST</p>
                                                                                        @endif
                                                                                        <p style="font-size: 0.8rem;">To buy from any one of the them, please click on the proceed button.</p>
                                                                                        <p style="margin-top: 30px;font-size: 0.85rem;font-weight: bold;letter-spacing: 0.3px;">For any assistance, please feel free to connect with our customer care at our number {{ config('constants.brokerConstant.tollfree_number')}} or drop an e-mail at <a href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a></p>
                                                                                        <!-- <p style="margin-top: 30px;font-size: 0.85rem;font-weight: bold;">For any assistance, please feel free to connect with our customer care at our toll free number 1800-12000-0055 or drop an e-mail at <a href="#">help@comparepolicy.com</a></p> -->
                                                                                        <div style="{{-- display: flex;flex-direction: column; --}}margin-top: 60px;">
                                                                                            <span>Yours Sincerely,</span><br><br>
                                                                                            <strong style="color: darkblue;">{{ config('app.name') }} Team</strong>
                                                                                            <!-- </div> -->
                                                                                        </div>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="background:#2f445c; font-size:11px; color:#fff; text-align:center;">
                                                                                    <div style="width:80%; padding:10px 0 10px; margin:0 auto;">
                                                                                        <!-- CIN : U74140DL2015PTC276540 Compare Policy Insurance Web
                                                                                        Aggregators Pvt Ltd. IRDAI Web Aggregator Registration
                                                                                        No. 010, License Code No IRDAI/WBA23/15 -->
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
                                                <td align="left" style="background:#efeff4;padding:0" valign="top" width="10"></td>
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
