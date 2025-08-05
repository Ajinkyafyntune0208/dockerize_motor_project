<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>

    <style>
        @media only screen and (max-width: 480px) {
            .padding-class {
                padding: 3px 0px !important;
            }

            .ic-logo {
                height: 40px !important;
                width: 55px !important;
            }

            .margin-class{
                margin: 10px 20px !important;
            }
        }
    </style>
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
                                                    width="100%">
                                                </td>
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
                                                                                    class="padding-class"
                                                                                    style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px"
                                                                                    valign="top">

                                                                                    <div style="margin: 20px 40px;" class="margin-class">
                                                                                        <p style="font: bold 1.1rem Calibri,Candara,Arial;color:{{ config('email.textColor') ?? '#d82f66' }};margin-bottom: 10px;padding: 0px;">
                                                                                            Dear {{ trim($mailData['name']) }},</p>

                                                                                        @yield('content')

                                                                                        <p style="margin-top:30px;font-size: 0.9rem;">
                                                                                            For any assistance, please feel free to connect with our customer care at our toll free number 
                                                                                            {{ config('constants.brokerConstant.tollfree_number') }} or drop an e-mail at 
                                                                                             <a href="mailto:{{ config('constants.brokerConstant.support_email') }}">
                                                                                                {{ config('constants.brokerConstant.support_email') }}
                                                                                            </a>
                                                                                        </p>
                                                                                        
                                                                                        <div style="margin-top: 30px;">
                                                                                            <p style="font-size: 0.9rem;">Yours Sincerely,</p>
                                                                                            <p style="font-size: 0.9rem;color: {{ config('email.textColor') ?? '#012870' }}">
                                                                                                <strong>{{ config('app.name') }}</strong>
                                                                                            </p>
                                                                                        </div>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="background: {{ config('email.textColor') ?? '#012870' }}; font-size:11px; color:#fff; text-align:center;">
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
