<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - Compare Car Insurance Policy</title>
</head>

<body>
    <table align="center" border="0" cellpadding="0" cellspacing="0" style="background:#efeff4; padding:0; margin:0;"
        width="100%">
        <tbody>
            <tr>
                <td></td>
                <td align="center" style="padding:0;border-collapse:collapse" valign="top" width="600">
                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="width: 100%">
                        <tbody>
                            {{-- <tr>
                                <td align="left" style="padding:0;background:#d39494" valign="top">
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tbody>

                                            <td align="left">
                                                <center>
                                                    <p>Aditya Birla Insurance Brokers Limited</p>
                                                </center>
                                            </td>
                                            <!-- https://www.comparepolicy.com/cp/welcome-emails/images/cp-logo.png -->
                                            <td align="right">
                                                <a href="{{ config('email_logo_redirection_url') }}"
                                                    style="text-decoration:none;outline:none"><img
                                                        src="https://posp.adityabirlainsurancebrokers.com/public/images/logos/abibl_logo.png"
                                                        title="{{ config('app.name') }}" width="160" /> </a>
                                            </td>
                                        </tbody>
                                    </table>
                                </td>
                            </tr> --}}
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
                                                                                    <div
                                                                                        style="margin: 20px 40px; letter-spacing: 0.3px;">
                                                                                        <p
                                                                                            style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;">
                                                                                            Dear {{ 'Motor Team' }}
                                                                                        </p>
                                                                                        <p
                                                                                            style="font-size: 1.1rem;font-weight: bold;">
                                                                                            Thank You!</p>
                                                                                        <div style="padding: 0px 10px;">
                                                                                            <p
                                                                                                style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">
                                                                                                The MMV Daily Reports {{ now()->subDay()->startOfDay() .' - '. now()->subDay()->endOfDay() }}
                                                                                            </p>
                                                                                            <a href="{{ $data['file_name'] }}"
                                                                                                style="background: #1e00ffb0; color: #fff;border: none;border-radius: 4px;padding: 8px; text-decoration: none">Download
                                                                                                Report</a> <br>
                                                                                                <br>
                                                                                                <a href="{{ $data['file_name'] }}">Download Report Link</a>
                                                                                            <div
                                                                                                style="margin-top: 60px;">
                                                                                                <span>Warm
                                                                                                    Regards,</span><br><br>
                                                                                                <strong>Fyntune Motor
                                                                                                    Team</strong>
                                                                                            </div>
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
