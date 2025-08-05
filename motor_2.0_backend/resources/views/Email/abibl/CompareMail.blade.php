@extends('Email.abibl.layout')
@section('content')
    <tr>
        <td align="left" colspan="2"
            style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px"
            valign="top">
            <div style="margin: 20px 40px; letter-spacing: 0.3px;">
                <p style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;">Dear
                    {{ $mailData['name'] }}</p>
                <div style="padding: 0px 10px;">

                    <p style="font-size: 0.9rem;font-weight: bold;">Thank you for visiting <a href="{{ $mailData['link'] }}">Link</a> for all
                        your insurance
                        needs. Compare and buy the best insurance plan as per your requirement from the
                        leading insurers.</p>

                    <p style="font-size: 0.9rem;">As per the below details shared by you. here’s a comparison pdf of features
                        of the selected plans. Choose the best Plan that suits you:</p>
                        
                    <p style="margin: 0;padding: 0; color: darkblue;font-size: 0.9rem;font-weight: bold;">Plan:
                        {{  $mailData["plan_type"]  }}</p>
        
                    <p style="font-size: 0.9rem;">These quotes are valid only till midnight today.</p>
                    <p style="font-size: 0.9rem;">For any further assistance, please call us on 1800 270 7000 or write to us
                        at <a
                            href="mailto:clientfeedback.abibl@adityabirlainsurancebrokers.com">clientfeedback.abibl@adityabirlainsurancebrokers.com</a>
                    </p>

                    <div style="margin-top: 30px;">
                        <p style="font-size: 0.9rem;">Stay Informed, Stay Insured</p>
                        <p style="font-size: 0.9rem;"><strong>Aditya Birla Insurance Brokers Limited</strong></p>
                    </div>

        </td>
    </tr>
@endsection
