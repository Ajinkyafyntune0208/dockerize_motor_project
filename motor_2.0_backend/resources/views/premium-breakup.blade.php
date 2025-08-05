@if (!empty($premium_details))
    @php
        $enquiryId = $premium_details['user_product_journey_id'];
        $own_damage  = [
            'basic_od_premium' => 'Basic Own Damage(OD)',
            'electric_accessories_value' => 'Electrical Accessories',
            'non_electric_accessories_value' => 'Non-Electrical Accessories',
            'bifuel_od_premium' => 'LPG/CNG Kit',
            'geo_extension_odpremium' => 'Geographical Extension',
            'loading_amount' => 'Loading Amount',
            'limited_own_premises_od' => 'Vehicle Limited to Premises (-)',
            'od_premium' => 'Total OD Premium (A)'
        ];

        $liability = [
            'basic_tp_premium' => 'Third Party Liability',
            'tppd_discount' => 'TPPD Discounts (-)',
            'unnamed_passenger_pa_cover' => 'PA For Unnamed Passenger',
            'pa_additional_driver' => 'Additional PA Cover To Paid Driver',
            'll_paid_driver' => 'Legal Liability To Paid Driver',
            'll_paid_cleaner' => 'Legal Liability To Paid Cleaner',
            'll_paid_conductor' => 'Legal Liability To Paid Conductor',
            'll_paid_employee' => 'Legal Liability To Paid Employee',
            'bifuel_tp_premium' => 'LPG/CNG Kit TP',
            'compulsory_pa_own_driver' => 'Compulsory PA Cover For Owner Driver',
            'geo_extension_tppremium' => 'Geographical Extension',
            'limited_own_premises_tp' => 'Vehicle Limited to Premises (-)',
            'final_tp_premium' => 'Total Liability Premium (B)'
        ];

        $od_discounts = [
            'ncb_discount_premium' => 'Deduction of NCB',
            'voluntary_excess' => 'Voluntary Deductible',
            'anti_theft' => 'Anti-Theft',
            'other_discount' => 'Other Discounts'
        ];

        $totalDiscount = array_sum(array_intersect_key(
            $premium_details['details'],
            array_flip(array_keys($od_discounts))
        ));

        $od_discounts['Total Discount (C)'] = $totalDiscount;

        $addons = [
            'zero_depreciation' => 'Zero Depreciation',
            'tyre_secure' => 'Tyre Secure',
            'road_side_assistance' => 'Road Side Assistance',
            'return_to_invoice' => 'Return To Invoice',
            'key_replacement' => 'Key Replacement',
            'engine_protector' => 'Engine Protector',
            'imt_23' => 'IMT 23',
            'consumable' => 'Consumables',
            'ncb_protection' => 'Ncb Protection',
            'loss_of_personal_belongings' => 'Loss of Personal Belongings',
            'eme_cover' => 'Eme Cover',
            'accident_shield' => 'Accident Shield',
            'conveyance_benefit' => 'Conveyance Benefit',
            'passenger_assist_cover' => 'Passenger Assist Cover',
            'wind_shield' => 'Wind Shield',
            'motor_protection' => 'Motor Protection',
            'battery_protect' => 'Battery Protect',
            'additional_towing' => 'Additional Towing'
        ];

        $totalAddon = array_sum(array_intersect_key(
            $premium_details['details'],
            array_flip(array_keys($addons))
        ));

        $addons['Total Addon Premium (D)'] = $totalAddon;

        $calculations = [
            'final_od_premium' => 'Total OD Payable',
            'final_tp_premium' => 'Total TP Payable',
            'net_premium' => 'Net Premium',
            'service_tax_amount' => 'GST',
            'final_payable_amount' => 'Gross Premium (incl. GST)'
        ];
        $res = \App\Http\Controllers\PremiumDetailController::verifyPremiumDetails($enquiryId);
        $verified = $res['status'] ?? false;
    @endphp
    
    <h3 class="{{!$verified ? 'text-danger' : ''}}">Premium Breakup&nbsp;&nbsp;
        <i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#premium_breakup"></i>
    </h3>
    <div class="row collapse all" id="premium_breakup">
        <div class="col-12 col-lg-6 mt-2">
            <table class="table table-bordered">
                <thead>
                    <th colspan="2" class="text-center">Own Damage</th>
                </thead>
                <tbody>
                    @foreach ($own_damage as $key => $value)
                    @if (!empty($premium_details['details'][$key]))
                        @if (end($own_damage) == $value)
                            <tr>
                                <td><b>{{$value}}</b></td>
                                <td><b>&#8377; {{($premium_details['details'][$key] ?? 0) + ($premium_details['details']['loading_amount'])}}</b></td>
                            </tr>
                        @else
                            <tr>
                                <td>{{$value}}</td>
                                <td>&#8377; {{$premium_details['details'][$key] ?? 0}}</td>
                            </tr>
                        @endif
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="col-12 col-lg-6 mt-2">
            <table class="table table-bordered">
                <thead>
                    <th colspan="2" class="text-center">Liability</th>
                </thead>
                <tbody>
                    @foreach ($liability as $key => $value)
                        @if (!empty($premium_details['details'][$key]))
                            @if (end($liability) == $value)
                                <tr>
                                    <td><b>{{$value}}</b></td>
                                    <td><b>&#8377; {{($premium_details['details'][$key] ?? 0)}}</b></td>
                                </tr>
                            @else
                                <tr>
                                    <td>{{$value}}</td>
                                    <td>&#8377; {{$premium_details['details'][$key] ?? 0}}</td>
                                </tr>
                            @endif
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="col-12 col-lg-6 mt-2">
            <table class="table table-bordered">
                <thead>
                    <th colspan="2" class="text-center">Own Damage Discounts</th>
                </thead>
                <tbody>
                    @foreach ($od_discounts as $key => $value)
                    @if (end($od_discounts) == $value && !empty($value))
                        <tr>
                            <td><b>{{$key}}</b></td>
                            <td><b>&#8377; {{$value}}<b></td>
                        </tr>
                    @else
                        @if (!empty($premium_details['details'][$key]))
                        <tr>
                            <td>{{$value}}</td>
                            <td>&#8377; {{$premium_details['details'][$key] ?? 0}}</td>
                        </tr>
                        @endif
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="col-12 col-lg-6 mt-2 table-sm">
            <table class="table table-bordered">
                <thead>
                    <th colspan="2" class="text-center">Addons</th>
                </thead>
                <tbody>
                    @foreach ($addons as $key => $value)
                    @if (end($addons) == $value && !empty($value))
                        <tr>
                            <td><b>{{$key}}</b></td>
                            <td><b>&#8377; {{$value}}</b></td>
                        </tr>
                    @else
                        @if (!empty($premium_details['details'][$key]))
                        <tr>
                            <td>{{$value}}</td>
                            <td>&#8377; {{$premium_details['details'][$key] ?? 0}}</td>
                        </tr>
                        @elseif (isset($premium_details['details']['in_built_addons'][$key]))
                        <tr>
                            <td>{{$value}}</td>
                            @if (empty($premium_details['details']['in_built_addons'][$key]))
                            <td>
                                <button class="btn btn-xs btn-primary p-1">included</button>
                            </td>
                            @else
                            <td>&#8377; {{$premium_details['details']['in_built_addons'][$key]}}</td>
                            @endif
                        </tr>
                        @endif
                    @endif
                    
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="col-12 mt-4">
            <table class="table table-bordered">
                <tbody>
                    @foreach ($calculations as $key => $value)
                    <tr>
                        <td><b>{{$value}}</b></td>
                        <td><b>&#8377; {{$premium_details['details'][$key] ?? 0}}</b></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif