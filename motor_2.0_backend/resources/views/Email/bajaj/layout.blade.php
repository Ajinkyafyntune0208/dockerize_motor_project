<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>

    <!--[if !mso]>-->
    <link href="https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,400i,700,700i" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet" />
    <!--<![endif]-->

    <style>
        @media only screen and (max-width: 480px) {
            .padding-class {
                padding: 3px 0px !important;
            }

            .ic-logo {
                height: 40px !important;
                width: 55px !important;
            }

            .margin-class {
                margin: 10px 20px !important;
            }
        }

        #outlook a {
            padding: 0;
        }

        .ExternalClass {
            width: 100%;
        }

        .ExternalClass,
        .ExternalClass p,
        .ExternalClass span,
        .ExternalClass font,
        .ExternalClass td,
        .ExternalClass div {
            line-height: 100%;
        }

        .es-button {
            mso-style-priority: 100 !important;
            text-decoration: none !important;
        }

        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }

        .es-desk-hidden {
            display: none;
            float: left;
            overflow: hidden;
            width: 0;
            max-height: 0;
            line-height: 0;
            mso-hide: all;
        }

        [data-ogsb] .es-button {
            border-width: 0 !important;
            padding: 10px 20px 10px 20px !important;
        }

        td .es-button-border:hover a.es-button-1 {
            padding: 0px !important;
            background: #ffffff !important;
            border-color: #ffffff !important;
        }

        td .es-button-border-2:hover {
            background: #ffffff !important;
        }

        [data-ogsb] .es-button.es-button-3 {
            padding: 5px 35px 0px 0px !important;
        }

        td .es-button-border:hover a.es-button-4 {
            background: #e10c09 !important;
            border-color: #e10c09 !important;
        }

        td .es-button-border-5:hover {
            background: #e10c09 !important;
        }

        [data-ogsb] .es-button.es-button-6 {
            padding: 10px 20px !important;
        }
    </style>
</head>

<body>
    <table align="center" border="0" cellpadding="0" cellspacing="0"
        style="background:#efeff4; padding:0; margin:0;" width="100%">
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
                                                            title="{{ config('app.name') }}" width="160" /> </a></td>
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

                                                                                    <div style="margin: 20px 40px;"
                                                                                        class="margin-class">
                                                                                        <p
                                                                                            style="font: bold 1.1rem Calibri,Candara,Arial;color:#ed1c24;margin-bottom: 10px;padding: 0px;">
                                                                                            Dear
                                                                                            {{ trim($mailData['name']) }},
                                                                                        </p>
                                                                                        @yield('content')

                                                                                        <p
                                                                                            style="margin-top:30px;font-size: 0.9rem;">
                                                                                            For any assistance, please feel free to connect us at
                                                                                            <b>{{ config('constants.brokerConstant.tollfree_number') }}</b>
                                                                                            or drop an
                                                                                            e-mail at <a
                                                                                                href="mailto:{{ config('constants.brokerConstant.support_email') }}"><b>{{ config('constants.brokerConstant.support_email') }}</b></a>
                                                                                        </p>

                                                                                        <div style="margin-top: 30px;">
                                                                                            <p
                                                                                                style="font-size: 0.9rem;">
                                                                                                Yours Sincerely,</p>
                                                                                            <p
                                                                                                style="font-size: 0.9rem;color:darkblue">
                                                                                                <strong>{{ config('app.name') }}</strong>
                                                                                            </p>
                                                                                        </div>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>

                                                                    <table class="es-wrapper" width="100%"
                                                                        cellspacing="0" cellpadding="0"
                                                                        style="mso-table-lspace: 0pt;mso-table-rspace: 0pt;border-collapse: collapse;border-spacing: 0px;padding: 0;margin: 0;width: 100%;height: 100%;background-repeat: repeat;background-position: center top;background-color: #efefef;">

                                                                        <tr style="border-collapse: collapse">
                                                                            <td valign="top"
                                                                                style="padding: 0; margin: 0">
                                                                                <table cellpadding="0" cellspacing="0"
                                                                                    class="es-footer" align="center"
                                                                                    style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;table-layout:fixed!important;width:100%;background-color:transparent;background-repeat:repeat;background-position:centertop;">
                                                                                    <tr
                                                                                        style="border-collapse: collapse">
                                                                                        <td align="center"
                                                                                            bgcolor="#ffffff"
                                                                                            style="padding: 0; margin: 0; background-color: #ffffff">
                                                                                            <table
                                                                                                class="es-footer-body"
                                                                                                cellspacing="0"
                                                                                                cellpadding="0"
                                                                                                align="center"
                                                                                                bgcolor="#ffffff"
                                                                                                style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;background-color:#ffffff;width:540px;">
                                                                                                <tr
                                                                                                    style="border-collapse: collapse">
                                                                                                    <td align="left"
                                                                                                        bgcolor="#ffffff"
                                                                                                        style="padding:0;margin:0;padding-top:20px;padding-left:20px;padding-right:20px;background-color:#ffffff;">
                                                                                                        <table
                                                                                                            cellpadding="0"
                                                                                                            cellspacing="0"
                                                                                                            width="100%"
                                                                                                            style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;
                            ">
                                                                                                            <tr
                                                                                                                style="border-collapse: collapse">
                                                                                                                <td align="center"
                                                                                                                    valign="top"
                                                                                                                    style="padding: 0; margin: 0; width: 497px">
                                                                                                                    <table
                                                                                                                        cellpadding="0"
                                                                                                                        cellspacing="0"
                                                                                                                        width="100%"
                                                                                                                        role="presentation"
                                                                                                                        style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                                        <tr
                                                                                                                            style="border-collapse: collapse">
                                                                                                                            <td align="center"
                                                                                                                                style="padding:0;margin:0;padding-top:5px;padding-bottom:10px;font-size:0;">
                                                                                                                                <table
                                                                                                                                    border="0"
                                                                                                                                    width="100%"
                                                                                                                                    height="100%"
                                                                                                                                    cellpadding="0"
                                                                                                                                    cellspacing="0"
                                                                                                                                    role="presentation"
                                                                                                                                    style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                                                    <tr
                                                                                                                                        style="border-collapse: collapse">
                                                                                                                                        <td
                                                                                                                                            style="padding: 0;margin: 0;border-bottom: 2px solid #999999;background: none;height: 1px;width: 100%;margin: 0px;">
                                                                                                                                        </td>
                                                                                                                                    </tr>
                                                                                                                                </table>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                    </table>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                        </table>
                                                                                                    </td>
                                                                                                </tr>

                                                                                                <tr
                                                                                                    style="border-collapse: collapse">
                                                                                                    <td align="left"
                                                                                                        bgcolor="#ffffff"
                                                                                                        style="padding:0;margin:0;padding-top:20px;padding-left:25px;padding-right:25px;background-color:#ffffff;">
                                                                                                        <table
                                                                                                            cellpadding="0"
                                                                                                            cellspacing="0"
                                                                                                            width="100%"
                                                                                                            style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                            <tr
                                                                                                                style="border-collapse: collapse">
                                                                                                                <td align="center"
                                                                                                                    valign="top"
                                                                                                                    style="padding: 0; margin: 0; width: 487px">
                                                                                                                    <table
                                                                                                                        cellpadding="0"
                                                                                                                        cellspacing="0"
                                                                                                                        width="100%"
                                                                                                                        role="presentation"
                                                                                                                        style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                                        <tr
                                                                                                                            style="border-collapse: collapse">
                                                                                                                            <td align="left"
                                                                                                                                style="padding: 0; margin: 0">
                                                                                                                                <p style="margin: 0;-webkit-text-size-adjust: none;-ms-text-size-adjust: none;mso-line-height-rule: exactly;font-family: montserrat, sans-serif;line-height: 12px;color: #333333;font-size: 8px;">
                                                                                                                                    <strong>Disclaimer:</strong>&nbsp;Bajaj
                                                                                                                                    Capital Insurance Broking Ltd. [(BCIBL)
                                                                                                                                    CIN: U67200DL2002PLC117625]having its
                                                                                                                                    registered office at Bajaj House, 97,
                                                                                                                                    Nehru Place, New Delhi- 110019 is an IRDAI
                                                                                                                                    licensed 'Composite Broker' [bearing
                                                                                                                                    License &nbsp;241,&nbsp;license dated
                                                                                                                                    09/01/2022 valid till 08/01/2025
                                                                                                                                    (originally licensed by IRDAI&nbsp;on
                                                                                                                                    09/01/2004 and renewed thereafter)
                                                                                                                                    rendering advice on various insurance
                                                                                                                                    covers, suitable to all kinds of insurance
                                                                                                                                    needs.<br /><br />This insurance
                                                                                                                                    advertisement has been brought out to
                                                                                                                                    provide information on Health insurance
                                                                                                                                    product. Advice on similar insurance
                                                                                                                                    product(s), other than above, in offer
                                                                                                                                    from various insurer(s) is also offered by
                                                                                                                                    BCIBL. Prospective client(s) are advised
                                                                                                                                    to go through all comparable products in
                                                                                                                                    offer before taking an investment
                                                                                                                                    decision. FOR MORE DETAILS ON RISK
                                                                                                                                    FACTORS, TERMS AND CONDITIONS PLEASE READ
                                                                                                                                    SALES BROCHURE CAREFULLY BEFORE PURCHASING
                                                                                                                                    THE INSURANCE POLICY/CONCLUDING A SALE. An
                                                                                                                                    Insurance Plan should not just be viewed
                                                                                                                                    as an ordinary investment, but should
                                                                                                                                    primarily be treated as a tool to protect
                                                                                                                                    your financial assets along with providing
                                                                                                                                    an opportunity to do disciplined
                                                                                                                                    investment. Please consult our experts to
                                                                                                                                    guide you in purchasing Insurance
                                                                                                                                    Solutions that suit your needs. This
                                                                                                                                    advertisement is not about any specific
                                                                                                                                    Health Insurance plan. Rather it is an
                                                                                                                                    awareness initiative about health
                                                                                                                                    insurance policies. These policies can be
                                                                                                                                    brought from any of the IRDAI registered
                                                                                                                                    Health&nbsp;Insurance Company. BCIBL,
                                                                                                                                    being a registered Insurance Broker,
                                                                                                                                    assist its clients to source these
                                                                                                                                    policies from the registered
                                                                                                                                    Health&nbsp;Insurance Company. Insurance
                                                                                                                                    is the subject matter of
                                                                                                                                    solicitation.&nbsp;Advt.&nbsp; UNI: xxxxxxx
                                                                                                                                </p>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                    </table>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                        </table>
                                                                                                    </td>
                                                                                                </tr>
                                                                                                <tr
                                                                                                    style="border-collapse: collapse">
                                                                                                    <td align="left"
                                                                                                        bgcolor="#ffffff"
                                                                                                        style="padding:0;margin:0;padding-top:5px;padding-left:20px;padding-right:20px;background-color:#ffffff;">
                                                                                                        <table
                                                                                                            cellpadding="0"
                                                                                                            cellspacing="0"
                                                                                                            width="100%"
                                                                                                            style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                            <tr
                                                                                                                style="border-collapse: collapse">
                                                                                                                <td align="center"
                                                                                                                    valign="top"
                                                                                                                    style="padding: 0; margin: 0; width: 497px">
                                                                                                                    <table
                                                                                                                        cellpadding="0"
                                                                                                                        cellspacing="0"
                                                                                                                        width="100%"
                                                                                                                        role="presentation"
                                                                                                                        style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                                        <tr
                                                                                                                            style="border-collapse: collapse">
                                                                                                                            <td align="center"
                                                                                                                                style="margin:0;padding-top:10px;padding-bottom:10px;padding-left:20px;padding-right:20px;font-size:0;">
                                                                                                                                <table
                                                                                                                                    border="0"
                                                                                                                                    width="100%"
                                                                                                                                    height="100%"
                                                                                                                                    cellpadding="0"
                                                                                                                                    cellspacing="0"
                                                                                                                                    role="presentation"
                                                                                                                                    style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                                                    <tr
                                                                                                                                        style="border-collapse: collapse">
                                                                                                                                        <td
                                                                                                                                            style="padding:0;margin:0;border-bottom:1px solid #cccccc;background:none;height:1px;width:100%;margin:0px;">
                                                                                                                                        </td>
                                                                                                                                    </tr>
                                                                                                                                </table>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                        <tr
                                                                                                                            style="border-collapse: collapse">
                                                                                                                            <td align="center"
                                                                                                                                style="padding:0;margin:0;padding-top:10px;">
                                                                                                                                <p
                                                                                                                                    style="margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:lora,georgia,'timesnewroman',serif;line-height:20px;color:#333333;font-size:13px;">
                                                                                                                                    <strong>Follow
                                                                                                                                        Bajaj
                                                                                                                                        Capital
                                                                                                                                        Insurance
                                                                                                                                        Broking
                                                                                                                                        Limited
                                                                                                                                        Social
                                                                                                                                        Accounts:&nbsp;</strong><br /><br />
                                                                                                                                </p>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                        <tr
                                                                                                                            style="border-collapse: collapse">
                                                                                                                            <td align="center"
                                                                                                                                style="padding: 0; margin: 0; font-size: 0">
                                                                                                                                <table
                                                                                                                                    cellpadding="0"
                                                                                                                                    cellspacing="0"
                                                                                                                                    class="es-table-not-adapt es-social"
                                                                                                                                    role="presentation"
                                                                                                                                    style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                                                    <tr
                                                                                                                                        style="border-collapse: collapse">
                                                                                                                                        <td align="center"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding:0;margin:0;padding-right:10px;">
                                                                                                                                            <a target="_blank"
                                                                                                                                                href="https://instagram.com/bajaj.capital.insurance?igshid=YmMyMTA2M2Y="
                                                                                                                                                style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;text-decoration:underline;color:#2e3951;font-size:13px;"><img
                                                                                                                                                    title="Instagram"
                                                                                                                                                    src="{{config('constants.brokerConstant.INSTAGRAM_SOURCE_URL')}}"
                                                                                                                                                    alt="Inst"
                                                                                                                                                    width="32"
                                                                                                                                                    height="32"
                                                                                                                                                    style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" /></a>
                                                                                                                                        </td>
                                                                                                                                        <td align="center"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding:0;margin:0;padding-right:10px;">
                                                                                                                                            <a target="_blank"
                                                                                                                                                href="https://www.linkedin.com/company/bajaj-capital-insurance-broking-ltd/"
                                                                                                                                                style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;text-decoration:underline;color:#2e3951;font-size:13px;"><img
                                                                                                                                                    title="Linkedin"
                                                                                                                                                    src="{{config('constants.brokerConstant.LINKEDIN_SOURCE_URL')}}"
                                                                                                                                                    alt="In"
                                                                                                                                                    width="32"
                                                                                                                                                    height="32"
                                                                                                                                                    style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" /></a>
                                                                                                                                        </td>
                                                                                                                                        <td align="center"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding:0;margin:0;padding-right:10px;">
                                                                                                                                            <a target="_blank"
                                                                                                                                                href="https://twitter.com/bcl_insurance?s=21&t=9wRdsU_9AgcU9kX3j5bhAQ"
                                                                                                                                                style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;text-decoration:underline;color:#2e3951;font-size:13px;"><img
                                                                                                                                                    title="Twitter"
                                                                                                                                                    src="{{config('constants.brokerConstant.TWITTER_SOURCE_URL')}}"
                                                                                                                                                    alt="Tw"
                                                                                                                                                    width="32"
                                                                                                                                                    height="32"
                                                                                                                                                    style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" /></a>
                                                                                                                                        </td>
                                                                                                                                        <td align="center"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding:0;margin:0;padding-right:10px;">
                                                                                                                                            <a target="_blank"
                                                                                                                                                href="https://www.facebook.com/bajajcapitalinsurance"
                                                                                                                                                style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;text-decoration:underline;color:#2e3951;font-size:13px;"><img
                                                                                                                                                    title="Facebook"
                                                                                                                                                    src="{{config('constants.brokerConstant.FACEBOOK_SOURCE_URL')}}"
                                                                                                                                                    alt="Fb"
                                                                                                                                                    width="32"
                                                                                                                                                    height="32"
                                                                                                                                                    style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" /></a>
                                                                                                                                        </td>
                                                                                                                                        <td align="center"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 0; margin: 0">
                                                                                                                                            <a target="_blank"
                                                                                                                                                href="https://www.youtube.com/@bajajcapital.insurance"
                                                                                                                                                style="-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;text-decoration:underline;color:#2e3951;font-size:13px;"><img
                                                                                                                                                    title="Youtube"
                                                                                                                                                    src="{{config('constants.brokerConstant.YOUTUBE_SOURCE_URL')}}"
                                                                                                                                                    alt="Yt"
                                                                                                                                                    width="32"
                                                                                                                                                    height="32"
                                                                                                                                                    style="display:block;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;" /></a>
                                                                                                                                        </td>
                                                                                                                                    </tr>
                                                                                                                                </table>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                        <tr
                                                                                                                            style="border-collapse: collapse">
                                                                                                                            <td align="center"
                                                                                                                                style="padding:20px;margin:0;font-size:0;">
                                                                                                                                <table
                                                                                                                                    border="0"
                                                                                                                                    width="100%"
                                                                                                                                    height="100%"
                                                                                                                                    cellpadding="0"
                                                                                                                                    cellspacing="0"
                                                                                                                                    role="presentation"
                                                                                                                                    style="mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;border-spacing:0px;">
                                                                                                                                    <tr
                                                                                                                                        style="border-collapse: collapse">
                                                                                                                                        <td
                                                                                                                                            style="padding:0;margin:0;border-bottom:1pxsolid#cccccc;background:none;height:1px;width:100%;margin:0px;">
                                                                                                                                        </td>
                                                                                                                                    </tr>
                                                                                                                                </table>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                        <tr
                                                                                                                            style="border-collapse: collapse">
                                                                                                                            <td align="center"
                                                                                                                                style="padding: 0; margin: 0">
                                                                                                                                <p
                                                                                                                                    style="margin:0;-webkit-text-size-adjust:none;-ms-text-size-adjust:none;mso-line-height-rule:exactly;font-family:montserrat,sans-serif;line-height:18px;color:#060000;font-size:12px;">
                                                                                                                                    Please connect with us for more queries at
                                                                                                                                    {{ config('constants.brokerConstant.support_email') }}
                                                                                                                                </p>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                    </table>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                            <tr
                                                                                                                style="border-collapse: collapse">
                                                                                                                <td align="center"
                                                                                                                    valign="top"
                                                                                                                    style="padding: 0; margin: 0; width: 560px">
                                                                                                                    <table
                                                                                                                        cellpadding="0"
                                                                                                                        cellspacing="0"
                                                                                                                        width="100%"
                                                                                                                        role="presentation"
                                                                                                                        style="mso-table-lspace: 0pt;mso-table-rspace: 0pt;border-collapse: collapse;border-spacing: 0px;">
                                                                                                                        <tr
                                                                                                                            style="border-collapse: collapse">
                                                                                                                            <td align="center"
                                                                                                                                style="margin: 0;padding-top: 5px;padding-bottom: 10px;padding-left: 10px;padding-right: 10px;font-size: 0;">
                                                                                                                                <table
                                                                                                                                    border="0"
                                                                                                                                    width="100%"
                                                                                                                                    height="100%"
                                                                                                                                    cellpadding="0"
                                                                                                                                    cellspacing="0"
                                                                                                                                    role="presentation"
                                                                                                                                    style="mso-table-lspace: 0pt;mso-table-rspace: 0pt;border-collapse: collapse;border-spacing: 0px;">
                                                                                                                                    <tr
                                                                                                                                        style="border-collapse: collapse">
                                                                                                                                        <td
                                                                                                                                            style="padding: 0;margin: 0;border-bottom: 3px solid #efefef;background: none;height: 1px;width: 100%;margin: 0px;">
                                                                                                                                        </td>
                                                                                                                                    </tr>
                                                                                                                                </table>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                    </table>
                                                                                                                </td>
                                                                                                            </tr>
                                                                                                        </table>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </td>
                                                                                    </tr>
                                                                                </table>
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                                <td align="left" style="background:#efeff4;padding:0"
                                                    valign="top" width="10"></td>
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
