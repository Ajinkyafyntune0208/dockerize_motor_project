@extends('Email.abibl.layout')
@section('content')
    <tr>
        <td align="left" colspan="2"
            style="background:#ffffff;font:normal 15px 'Lato','Helvetica Neue',Helvetica,Tahoma,Arial,sans-serif;color:#314451;text-align:left;color:#314452;line-height:1.3em;padding:20px"
            valign="top">
            <div style="margin: 20px 40px; letter-spacing: 0.3px;">

                <p style="font: bold 18px Calibri,Candara,Arial;color: #da154c;margin-bottom: 10px;padding: 0px;">Dear
                    {{ $mailData['name'] }},</p>


                <div style="padding: 10px 5px;letter-spacing: 0;font-size: 0.8rem;color: #222222;font-family: sans-serif;">

                    <p style="font-size: 0.9rem;">Thank you for visiting <a href={{ $mailData['url'] }}
                            target="_blank">link</a> for all your insurance needs. compare and buy the best insurance plan
                        from leading insurers. Please find below quotes from leading insurers as per your requirement.</p>

                    <table style="width: 100%;">
                        <thead style="background: #0f4373;color: #fff;font-size: 0.7rem;">
                            <tr>
                                <th colspan="3" style="text-align:center;padding:10px 5px;">CHOOSE THE BEST INSURANCE
                                    PLAN THAT SUITS YOUR NEEDS</th>
                            </tr>
                            <tr>
                                <th style="padding-left: 15px;">Product Name</th>
                                <th style="text-align: center;">Premium</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mailData['quotes'] as $quote)
                                <tr style="background: #f0fafe;color: #012870;">
                                    <td style="display: flex;">
                                        <img src="{{ $quote['logo'] }}" alt="" width="100px" height="60px">
                                        <p
                                            style="margin: 0;font-size: 0.7rem;font-weight: bold;align-self: center;padding-left: 15px;">
                                            {{ $quote['name'] }} <br> IDV: Rs.{{ $quote['idv'] }}</p>
                                    </td>
                                    @if(isset($mailData['gstSelected']) && $mailData['gstSelected'] === "Y")
                                    <td style="text-align: center;">Rs. {{ $quote['finalPremium'] }}</td>
                                    @else
                                    <td style="text-align: center;">Rs. {{ $quote['premium'] }}</td>
                                    @endif
                                    <td style="text-align: center;">
                                        <a href="{{ $quote['action'] }}"
                                            style="background: #0000ffb0;color: #fff;border: none;border-radius: 4px;padding: 8px;">Proceed</a>
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
                    {{-- <div style="font-size: 12px; color: #000; margin-top: 10px">Generated quotes based on :<br>
					<strong>Insurance Type</strong> : {{$mailData['quote_data']->policy_type}},
					<strong>Vehicle Name</strong> : {{$mailData['quote_data']->manfacture_name.' '.$mailData['quote_data']->model_name.' '.$mailData['quote_data']->version_name }}, 
					<strong>Owner Type</strong> : {{(($mailData['corporate_data']->vehicle_owner_type=='I') ? 'Individual':'Company')}},
					<strong>First Reg Date</strong> :{{$mailData['corporate_data']->vehicle_register_date}},
					<strong>Existing Policy Expiry Date</strong> : {{$mailData['corporate_data']->previous_policy_expiry_date}},
					<strong>No Claim Bonus</strong> : {{$mailData['quote_data']->previous_ncb}} %,
					<strong>Registration No</strong> : {{$mailData['quote_data']->rto_code}}
					</div> --}}

                    <p style="font-size: 0.9rem;">We recommend you also consider add-on covers for extra protection. Once
                        you choose you can click on the 'Proceed' button to buy the insurance plan.</p>
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
