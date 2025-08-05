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

                    <p style="font-size: 0.9rem;font-weight: bold;">
                        Thank you for choosing Aditya Birla Insurance Broker Limited as your trusted partner
                        for all your insurance needs. Compare and get the best quotes from leading insurers.</p>

                    <p style="font-size: 1rem;">Your account has been created to help us serve you better. You can log in
                        using your registered mobile number and view all your saved quotes and details of your insurance
                        policies bought through us.</p>

                    <p style="font-size: 1rem;">For any further assistance, please call us on 1800 270 7000 or write to us
                        at <a
                            href="mailto:clientfeedback.abibl@adityabirlainsurancebrokers.com">clientfeedback.abibl@adityabirlainsurancebrokers.com</a>
                    </p>

                    <div style="margin-top: 30px;">
                        <p style="font-size: 1rem;">Stay Informed, Stay Insured</p>
                        <p style="font-size: 1rem;"><strong>Aditya Birla Insurance Brokers Limited</strong></p>
                    </div>
                </div>
        </td>
    </tr>
@endsection
