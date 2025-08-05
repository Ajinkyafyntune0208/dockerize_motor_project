<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policy Report</title>
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
                                                <!-- <td style="text-align:left;padding:10px 0px 12px 30px" valign="bottom" width="100%"><a href="{{ config('email_logo_redirection_url') }}" style="text-decoration:none;outline:none"><img src="https://www.comparepolicy.com/cp/welcome-emails/images/cp-logo.png" title="ComparePolicy.com" width="160" /> </a></td> -->
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
                                                                    <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid;">
                                                                        <tbody>
                                                                            @if(count($previous_date_reports) > 0)
                                                                            <tr>
                                                                                <td style="padding:10px 0;border-bottom:solid 1px #b6b6b6; border-top:solid 1px #b6b6b6">
                                                                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="width:600px;min-width:320px;padding:10px">
                                                                                        <thead>
                                                                                            <tr>
                                                                                                <th align="center" colspan="4" style="padding:5px 0 15px; font:normal 18px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#fa7a57">
                                                                                                    Number of Policy issued on {{date('01/01/Y')}}
                                                                                                </th>
                                                                                            </tr>

                                                                                            <tr>
                                                                                                <th>Product</th>
                                                                                                <th>Count Of Policy</th>
                                                                                                <th>Premium</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>

                                                                                            @foreach($previous_date_reports as $report)
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->product_sub_type_code}} </p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->count_of_policy}} </p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->premium_amount}} </p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                            @endif

                                                                            @if(count($current_month_reports) > 0)
                                                                            <tr>
                                                                                <td style="padding:10px 0;border-bottom:solid 1px #b6b6b6; border-top:solid 1px #b6b6b6">
                                                                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="width:600px;min-width:320px;padding:10px">
                                                                                        <thead>
                                                                                            <tr>
                                                                                                <th align="center" colspan="4" style="padding:5px 0 15px; font:normal 18px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#fa7a57">
                                                                                                    Number of Policy issued on {{date('01/m/Y')}} - {{date('d/m/Y', strtotime("-1 days"))}}
                                                                                                </th>
                                                                                            </tr>

                                                                                            <tr>
                                                                                                <th>Product</th>
                                                                                                <th>Count Of Policy</th>
                                                                                                <th>Premium</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>

                                                                                            @foreach($current_month_reports as $report)
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->product_sub_type_code}} </p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->count_of_policy}} </p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->premium_amount}} </p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                            @endif

                                                                            @if(count($current_year_reports) > 0)
                                                                            <tr>
                                                                                <td style="padding:10px 0;border-bottom:solid 1px #b6b6b6; border-top:solid 1px #b6b6b6">
                                                                                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="width:600px;min-width:320px;padding:10px">
                                                                                        <thead>
                                                                                            <tr>
                                                                                                <th align="center" colspan="4" style="padding:5px 0 15px; font:normal 18px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#fa7a57">
                                                                                                    Number of Policy issued on {{date('01/01/Y')}} - {{date('d/m/Y', strtotime("-1 days"))}}
                                                                                                </th>
                                                                                            </tr>

                                                                                            <tr>
                                                                                                <th>Product</th>
                                                                                                <th>Count Of Policy</th>
                                                                                                <th>Premium</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>

                                                                                            @foreach($current_year_reports as $report)
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->product_sub_type_code}} </p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->count_of_policy}} </p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p style="display:block; float:left; text-decoration:none; border:0;">{{$report->premium_amount}} </p>
                                                                                                </td>
                                                                                            </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                            @endif
                                                                            <tr>
                                                                                <td style="background:#2f445c; font-size:11px; color:#fff; text-align:center;">
                                                                                    <div style="width:80%; padding:10px 0 10px; margin:0 auto;">
                                                                                        CIN : U74140DL2015PTC276540 Compare Policy Insurance Web
                                                                                        Aggregators Pvt Ltd. IRDAI Web Aggregator Registration
                                                                                        No. 010, License Code No IRDAI/WBA23/15
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