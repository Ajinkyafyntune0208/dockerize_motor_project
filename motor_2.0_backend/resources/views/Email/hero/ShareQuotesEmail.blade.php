@php $folder = config('constants.motorConstant.SMS_FOLDER'); @endphp
@extends("Email.{$folder}.layout")
@section('content')

    <div style="padding: 10px 5px;letter-spacing: 0;font-size: 0.9rem;color: #222222;font-family: sans-serif;">Thank you for placing your insurance inquiry at
        <a href="{{ config("BROKER_WEBSITE")}}">{{ config("BROKER_WEBSITE")}}</a>. Here are the plans for your latest policy search for motor insurance:</div>
		
    <table style="width: 100%;">
        <thead style="background: #0f4373;color: #fff;font-size: 0.7rem;">
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
                        <img src="{{ $quote['logo'] }}" alt="" width="90px" height="60px">
                        <p style="margin: 0;font-size: 0.7rem;font-weight: bold;align-self: center;padding-left: 15px;">
                            {{ $quote['name'] }} <br> IDV: Rs.{{ $quote['idv'] }}</p>
                    </td>

                    @if (isset($mailData['gstSelected']) && $mailData['gstSelected'] === 'Y')
                        <td style="text-align: center;font-size: 0.9rem;">Rs. {{ $quote['finalPremium'] }}</td>
                    @else
                        <td style="text-align: center;font-size: 0.9rem;">Rs. {{ $quote['premium'] }}</td>
                    @endif

                    <td style="text-align: center;">
                        <a href="{{ $quote['action'] }}"><button style="background:#ed1c24;border-radius: 4px;padding: 4px;color: #fff;border: none;">Buy NOW</button></a>
                    </td>

                </tr>
            @endforeach
        </tbody>
    </table>

    @if (isset($mailData['gstSelected']) && $mailData['gstSelected'] === 'Y')
        <p style="font-size: 0.78rem;text-align: center;color: red;font-size: sans-serif;">*Above mentioned quotes are
            inclusive of GST</p>
    @else
        <p style="font-size: 0.78rem;text-align: center;color: red;font-size: sans-serif;">*Above mentioned quotes are
            exclusive of GST</p>
    @endif

	@endsection
