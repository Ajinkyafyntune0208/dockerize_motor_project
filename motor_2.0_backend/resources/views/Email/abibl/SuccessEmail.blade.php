@extends('Email.abibl.layout')
@section('content')
    <tr>
        <td align="left" colspan="2"
            style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px"
            valign="top">
            <div style="margin: 20px 40px; letter-spacing: 0.3px;">
                <p style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;">Dear
                    {{ $mailData['name'] }},</p>

                <div style="padding: 0px 10px;">

                    @if (!empty($mailData['link']))
                        <p style="font-size: 0.9rem;font-weight: bold;">Thank you for choosing us to buy your insurance
                            policy.
                            Your payment transaction was successful, and your policy has been issued and enclosed herewith.
                            Please
                            find below your policy details.</p>
                    @else
                        <p style="font-size: 0.9rem;font-weight: bold;">
                            Thank you for choosing us to buy your insurance policy.
                            Your payment transaction was successful. Please find below your policy details. </p>
                    @endif

                    <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Product Name:
                        {{ $mailData['product_name'] }}</p>
                    <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Policy Number:
                        {{ $mailData['policy_number'] }}</p>
                    <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Policy Start
                        Date:
                        {{ $mailData['policy_start_date'] }}</p>
                    <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Policy End
                        Date:
                        {{ $mailData['policy_end_date'] }}</p>
                    <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Premium
                        Amoumt:
                        Rs. {{ $mailData['final_payable_amount'] }}</p>


                    @if (!empty($mailData['link']))
                        <p style="font-size: 0.9rem;">You can also access and download your policy copy through your account
                            by logging in with your registered mobile
                            number. Always remember to check your policy details to avoid any hassles later.</p>
                    @else
                        <p style="font-size: 0.9rem;">You can also access your policy details by logging in to ‘My Account
                            with
                            your registered mobile number.
                            Always remember to check your policy details to avoid any hassles later.
                            The policy copy will be sent to your registered email id within 7 working days.</p>
                    @endif

                    <p style="font-size: 0.9rem;">For any further assistance, please call us on 1800 270 7000 or write
                        to us
                        at <a
                            href="mailto:clientfeedback.abibl@adityabirlainsurancebrokers.com">clientfeedback.abibl@adityabirlainsurancebrokers.com</a>
                    </p>

                    <div style="margin-top: 30px;">
                        <p style="font-size: 0.9rem;">Stay Informed, Stay Insured</p>
                        <p style="font-size: 0.9rem;"><strong>Aditya Birla Insurance Brokers Limited</strong></p>
                    </div>
                </div>
        </td>
    </tr>
@endsection
