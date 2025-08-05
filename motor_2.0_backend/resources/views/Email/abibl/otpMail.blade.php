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
                    <td align="left" style="padding:0;background:#d39494" valign="top">
                        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tbody>

                            <td align="left">
                                <center><p>Aditya Birla Insurance Brokers Limited</p></center>
                            </td>
                            <!-- https://www.comparepolicy.com/cp/welcome-emails/images/cp-logo.png -->
                            <td align="right" >
                                <a href="{{ config('email_logo_redirection_url') }}" style="text-decoration:none;outline:none"><img src="https://posp.adityabirlainsurancebrokers.com/public/images/logos/abibl_logo.png" title="{{ config('app.name') }}" width="160" /> </a>
                            </td>
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
                                            <td align="center"
                                                style="padding:0;font:normal 14px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;line-height:1.4em;background:#f9fafc"
                                                valign="top">
                                                <table align="left" border="0" cellpadding="0" cellspacing="0"
                                                       width="100%">
                                                    <tbody>
                                                    <tr>
                                                        <td align="left" colspan="2"
                                                            style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px"
                                                            valign="top">
                                                            <div style="margin: 40px 40px;">
                                                                <p
                                                                    style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;">
                                                                    Dear {{$mailData['name']}}
                                                                </p>
                                                                <p style="font-size: 0.9rem;">
                                                                    The OTP for doing
                                                                    payment
                                                                    {{ config('app.name') }}
                                                                    <br>
                                                                {{-- <h3
                                                                    style="text-align: center;">
                                                                    {{ $mailData['otp'] }}
                                                                </h3> --}}
                                                                </p>
                                                                <p style="font-size: 0.9rem;">Please validate the proposal by entering the OTP: <b>{{ $mailData['otp'] }}</b></p>
                                                                <p style="font-size: 0.9rem;">Make/Model: <b>{{ $mailData['make_model'] }}</b></p>
                                                                <p style="font-size: 0.9rem;">Renewal NCB: <b>{{ $mailData['applicable_ncb'] }}</b> </p>
                                                                <p style="font-size: 0.9rem;">Reg year: <b>{{ $mailData['vehicle_manf_year'] }}</b></p>
                                                                <p style="font-size: 0.9rem;">Break in: <b>{{ $mailData['is_breakin_case'] }}</b></p>
                                                                <p style="font-size: 0.9rem;">Premium:Rs <b>{{ $mailData['final_payable_amount'] }}</b></p>
                                                                <p>
                                                                    Thanks, <br>
                                                                    <b>{{ config('app.name') }}</b>
                                                                </p>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <tr align="center">

                                                        <td align="justify">
                                                            <img src="https://posp.adityabirlainsurancebrokers.com/public/images/contact.png" alt="Contact Logo">
                                                        </td>

                                                    </tr>

                                                    <tr align="center">
                                                        <td align="justify">
                                                            <strong>Registered Office: </strong>: Indian Rayon Compound, Veraval, Gujarat 362 266. Corporate Office: One World Centre, Tower-1, 7th floor, Jupiter Mill Compound, 841, SenapatiBapat Marg, Elphinstone Road, Mumbai 400 013 | Tel. No.: +91 22 4356 8585. IRDAI License Number: 146 | CIN: U99999GJ2001PLC062239 | Type of License: Composite License | Validity: 9th April 2021.
                                                            <br>
                                                            <br> Advice, graphics, images and information contained in this mailer is presented for information purposes. Insurance is a subject matter of solicitation. The information contained in this leaflet should not be considered exhaustive and the user should read and understand the product information, including the scopes of cover, terms, conditions, exclusions limitations and other risk factors carefully. Aditya Birla Insurance Brokers Limited shall in no event be held liable to any party for any direct, indirect, implied, punitive, special, incidental or other consequential damages arising directly or indirectly for any inaccuracy or any information provided contained in this leaflet. None of these items may be copied, reproduced, posted, or otherwise distributed in any manner without prior written consent of Aditya Birla Insurance Brokers Limited.ISO 9001 Quality Management certified by BSI under certificate number FS 611893. Aditya Birla Insurance Brokers Limited, Aditya Birla Health Insurance
                                                            Co. Limited and Aditya Birla Sun Life Insurance Company Limited are part of the same promoter group.
                                                            <br>
                                                            <center>(C) Aditya Birla Insurance Brokers Limited. All rights reserved.</center>
                                                        </td>
                                                    </tr>

                                                    <tr align="center">
                                                        <td align="justify">
                                                            <div >
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
