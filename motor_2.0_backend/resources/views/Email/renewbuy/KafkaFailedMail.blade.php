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
                    <table align="center" border="0" cellpadding="0" cellspacing="0">
                        <tbody>
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
                                                                                            Dear {{ $failedData['name']
                                                                                            }},
                                                                                        </p>
                                                                                        <p
                                                                                            style="font-size: 1.1rem;font-weight: bold;">
                                                                                            Kafka Validation - Data Push
                                                                                            issue!</p>
                                                                                        <div style="padding: 0px 10px;">
                                                                                            <p
                                                                                                style="margin-top: 2rem;margin-bottom: 1.5rem;font-size: 0.9rem;font-weight: bold;">
                                                                                                Validation Triggered for the
                                                                                                following trace ID :</p>
                                                                                            <p
                                                                                                style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">
                                                                                                Trace ID : {{
                                                                                                $failedData['trace_id']
                                                                                                }}</p>
                                                                                            <p
                                                                                                style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">
                                                                                                Validation Error : {{
                                                                                                $failedData['error_msg']
                                                                                                }}</p>
                                                                                            <p
                                                                                                style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Kafka Logs : 
                                                                                                <a href="{{ config('app.url') . '/admin/kafka-logs?enquiryId=' . $failedData['trace_id'] }}"
                                                                                                    style="text-decoration:none;outline:none">Click here.</a>
                                                                                            </p>
                                                                                            <div
                                                                                                style="margin-top: 60px;">
                                                                                                <span>Yours
                                                                                                    Sincerely,</span>
                                                                                            </div>
                                                                                            <div
                                                                                                style="margin-top: 20px;">
                                                                                                <strong class="mt-5"
                                                                                                    style="color: darkblue;">{{
                                                                                                    config('app.name')
                                                                                                    }}</strong>

                                                                                            </div>
                                                                                        </div>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td
                                                                                    style="background:#2f445c; font-size:11px; color:#fff; text-align:center;">
                                                                                    <div
                                                                                        style="width:80%; padding:10px 0 10px; margin:0 auto;">
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
