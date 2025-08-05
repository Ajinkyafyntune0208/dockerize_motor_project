@extends('Email.policy-era.layout')
@section('content')
    <div style="padding: 10px 5px;font-size: 0.9rem;color: #222222;font-family: sans-serif;">As
        per the details shared by you for purchasing {{ $mailData['section'] }} Insurance on our website,
        following are the quotes from different insurers.</div>

    <table style="width: 100%;">
        <thead style="background: #71b5cd;color: #fff;font-size: 0.7rem;">
            <tr>
                <th style="padding-left: 15px;">Product Name</th>
                <th style="padding:6px;text-align: center;">Premium</th>
                <th style="text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($mailData['quotes'] as $quote)
                <tr style="background: #f0fafe;color: #012870;">
                    <td style="display: flex;">
                        <img class="ic-logo" src="{{ $quote['logo'] }}" alt="{{ $quote['name'] }}"
                            title="{{ $quote['name'] }}" width="85px" height="60px">
                        <p style="margin: 0;font-size: 0.7rem;font-weight: bold;align-self: center;padding-left: 15px;">
                            {{ $quote['name'] }} <br> IDV: Rs.{{ $quote['idv'] }}</p>
                    </td>
                    @if(isset($mailData['gstSelected']) && $mailData['gstSelected'] === "Y")
                    <td style="text-align: center;">Rs. {{ $quote['finalPremium'] }}</td>
					@else
						<td style="text-align: center;">Rs. {{ $quote['premium'] }}</td>
					@endif
                    <td style="text-align: center;">
                        <a href="{{ $quote['action'] }}"
                            style="background: #71b5cd;color: #fff;border: none;border-radius: 4px;padding: 6px;">Proceed</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(isset($mailData['gstSelected']) && $mailData['gstSelected'] === "Y")
		<p style="font-size: 0.78rem;text-align: center;color: red;font-size: sans-serif;">*Above mentioned quotes are inclusive of GST</p>
	@else
	   <p style="font-size: 0.78rem;text-align: center;color: red;font-size: sans-serif;">*Above mentioned quotes are exclusive of GST</p>
	@endif
    <p style="font-size: 0.9rem;">To buy from any one of the them, please click on the proceed button.</p>
@endsection
