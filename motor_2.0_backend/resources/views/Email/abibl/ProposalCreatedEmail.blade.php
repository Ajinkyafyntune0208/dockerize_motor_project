@extends('Email.abibl.layout')
@section('content')
<tr>
    <td align="left" colspan="2"
        style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px"
        valign="top">
        <div style="margin: 40px 40px;">
            <p style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;">Dear
                {{ $mailData['name'] }}</p>
            <div style="padding: 0px 10px;">
                <p style="margin-top: 2.5rem;margin-bottom: 1.5rem;font-size: 0.9rem;;font-weight: bold;">You are just
                    few steps away from choosing the best insurance plan for you and your loved ones.</p>
                <p style="font-size: 0.9rem;;">To complete your transaction please click here: <br>
                    <a href="{{ $mailData['link'] }}">{{ $mailData['link'] }}</a>
                </p>
                <p>Kindly note that this link will be valid only till {{ today()->format('jS F Y') }} at midnight.</p>
                <p style="font-size: 0.9rem;;">For any further assistance, please call us on 1800 270 7000 or write to
                    us at <a
                        href="mailto:clientfeedback.abibl@adityabirlainsurancebrokers.com">clientfeedback.abibl@adityabirlainsurancebrokers.com</a>
                </p>

                <div style="margin-top: 30px;">
                    <p style="font-size: 0.9rem;;">Stay Informed, Stay Insured</p>
                    <p style="font-size: 0.9rem;;"><strong>Aditya Birla Insurance Brokers Limited</strong></p>
                </div>
            </div>
    </td>
</tr>
@endsection
