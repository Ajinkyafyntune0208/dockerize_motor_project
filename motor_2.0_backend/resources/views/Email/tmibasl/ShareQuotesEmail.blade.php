@extends('Email.tmibasl.layout')
@section('content')
    <p style="font: bold 18px Calibri,Candara,Arial;color: #903bf0;margin-bottom: 10px;padding: 0px;">Dear
        {{ $mailData['name'] }},</p>
    <p style="font-size: 0.9rem;">Hope you are doing well!</p>
    <p style="font-size: 0.9rem;">Thank you for choosing us to cater your {{ $mailData["productName"] }} needs.</p>
    <p style="font-size: 0.9rem;">Attaching herewith the {{ $mailData["productName"] }} quotes based on the details shared by you on
        LifeKaPlan. </p>

    <table style="width: 100%;">
        <thead style="background: #0099f2;color: #fff;font-size: 0.8rem;">
            <tr style="padding-top: 5px;padding-bottom: 5px;">
                <th style="padding: 8px;">Product Name</th>
                <th style="padding: 8px;text-align: center;">Premium</th>
                <th style="padding: 8px;text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($mailData['quotes'] as $quote)
                <tr style="background: #f0fafe;color: #0099f2;">
                    <td style="display: flex;">
                        <img class="ic-logo" src="{{ $quote['logo'] }}" alt="" width="100px" height="60px">
                        <p style="margin: 0;font-size: 0.7rem;font-weight: bold;align-self: center;padding-left: 15px;">
                            {{ $quote['name'] }} <br> IDV: Rs.{{ $quote['idv'] }}</p>
                    </td>
                    @if(isset($mailData['gstSelected']) && $mailData['gstSelected'] === "Y")
						<td style="text-align: center;font-size: 0.9rem;">Rs. {{ $quote['finalPremium'] }}</td>
					@else
				        <td style="text-align: center;font-size: 0.9rem;">Rs. {{ $quote['premium'] }}</td>
					@endif
                    <td style="text-align: center;">
                        <a href="{{ $quote['action'] }}"><button style="background: #0099f2;color: #fff;border: none;border-radius: 4px;padding: 10px 5px;cursor:pointer;">Proceed</button></a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(isset($mailData['gstSelected']) && $mailData['gstSelected'] === "Y")
        <p style="font-size: 0.78rem;text-align: center;color: red;font-size: sans-serif;">*Above mentioned quotes are inclusive of GST</p>
	@else
    <p style="font-size: 0.78rem;text-align: center;color: red;font-size: sans-serif;">*Above mentioned quotes
        are exclusive of GST</p>
	@endif
    <p style="font-size: 0.9rem;">Kindly click on the proceed button to buy one of the above listed insurance cover.
    </p>
    <p style="margin-top:20px;font-size: 0.9rem;">For any assistance,
        please feel free to connect us at {{ config('constants.brokerConstant.tollfree_number') }} or drop an
        e-mail at <a
            href="mailto:{{ config('constants.brokerConstant.support_email') }}">{{ config('constants.brokerConstant.support_email') }}</a>
    </p>
@endsection