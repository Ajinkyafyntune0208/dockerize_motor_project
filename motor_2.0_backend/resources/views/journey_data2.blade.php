@extends('admin_lte.layout.app', ['activePage' => 'journey-data', 'titlePage' => __('Journey Data')])
@section('content')
<!-- partial -->
<style>
    i {
        cursor: pointer;
    }
</style>
@if (session('error'))
<div class="alert alert-danger mt-3 py-1">
    {{ session('error') }}
</div>
@endif

@if (session('success'))
<div class="alert alert-success mt-3 py-1">
    {{ session('success') }}
</div>
@endif
<div class="card">
    <div class="card-body">
        <div class="form-group">
            <form action="" method="get">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <Label class="required">Enquiry Id</Label>
                            <input type="text" name="enquiry_id"
                                value="{{ old('enquiry_id', request()->enquiry_id) }}" required
                                class="form-control check" placeholder="Enquiry ID" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex align-items-center h-100">
                            <input type="submit" class="btn btn-outline-info btn-sm w-100">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex align-items-center h-100">
                            <button type="button" id="view_all" class="btn btn-primary">Show All</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @if (!empty($journey_data))
        <div class="row">
            <div class="col-md-6">
                <h3>Journey Data&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        for="btnControl" data-bs-target="#journey_data"></i></h3>
                @php
                # B2C Changes.
                if(empty($journey_data->agent_details[0]->seller_type) &&
                !empty($journey_data->agent_details[0]->user_id))
                $journey_data->agent_details[0]->seller_type = 'b2c';
                @endphp
                <div id="journey_data" class="collapse all">
                    <pre>
                    <table >
                        <tbody>
                            @php
                            function RecursiveTable($addons){
                                foreach ($addons as $key => $item){
                                    if (is_array($item) && !empty($item)) {
                                        $key = str_replace("_"," ",$key);
                                        if (!is_numeric($key)) {
                                            echo '<tr class="border border-1 border-dark fw-bold fs-1 text-capitalize" style="font-size:50px;margin-left:270px;!important"> <td class="px-2 " colspan ="2" style="font-size:13px;;margin-left:270px;!important">'.$key.':-</td> </tr>';
                                        }
                                        RecursiveTable($item);
                                    }
                                    else {
                                        if(is_null($item)){
                                            $item = 'null';
                                        }
                                        elseif (is_array($item) && empty($item)) {
                                            $item = '[]';
                                        }
                                        echo '<tr class="border border-1 border-dark">';
                                        echo ' <td class="px-2 bg-secondary text-capitalize" style="font-size:13px;">'. str_replace("_"," ",$key).'</td>';
                                        echo '<td class="border border-1 border-dark px-2 bg-secondary " style="font-size:13px;">'.$item.'</td>';
                                        '</tr>';
                                    }
                                }
                            }
                            if (empty($journey_data->getattributes())) {
                                echo '<td>'.'[]'.'</td>';
                            }
                            else {
                                RecursiveTable($journey_data->getattributes());
                            }
                            @endphp
                        </tbody>
                    </table> 
                    {{-- <span class="fs-4"style="margin:-270px;"> Smsotps</span>
                    <table>
                        <tbody>
                            @php
                            if (empty($journey_data->smsOtps->getattributes())) {
                                echo '<td>'.'[]'.'</td>';
                            }
                            else {
                                RecursiveTable($journey_data->smsOtps->getattributes());
                            }
                            @endphp
                        </tbody>
                    </table>     --}}
                </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Journey Stage&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        data-bs-target="#journey_Stage"></i></h3>
                <div id="journey_Stage" class="collapse all">
                    <pre>
                    <table>
                        <tbody>
                            @php
                                if (empty($journey_stage)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($journey_stage);
                                }
                                @endphp
                        </tbody>
                    </table> 
                    </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Selected Addons&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        data-bs-target="#selected_addons"></i></h3>
                <div id="selected_addons" class="collapse all">
                    <pre id="addons">
                    <table>
                        <tbody>
                            @php
                                if (empty($addons)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($addons);
                                }
                                @endphp
                        </tbody>
                    </table> 
                    </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Policy Details&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        data-bs-target="#policy_details"></i></h3>
                <div id="policy_details" class="collapse all">
                    <pre id="addons">
                        <table>
                            <tbody>
                                @php
                                if (empty($policy_details)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($policy_details);
                                }
                                @endphp
                            </tbody>
                        </table> 
                        </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Break-in Details&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        data-bs-target="#break_in_details"></i></h3>
                <div id="break_in_details" class="collapse all">
                    <pre>
                        <table>
                            <tbody>
                                @php
                                if (empty($breakin_status)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($breakin_status);
                                }
                                @endphp
                            </tbody>
                        </table> 
                        </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Payment Response&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        data-bs-target="#payment_response"></i></h3>
                <div id="payment_response" class="collapse all">
                    <pre>
                        <table>
                            <tbody>
                                @php
                                if (empty($payment_response)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($payment_response);
                                }
                                @endphp
                            </tbody>
                        </table> 
                        </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Quote Data&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        data-bs-target="#quote_data"></i></h3>
                <div id="quote_data" class="collapse all">
                    <pre>
                        <table>
                            <tbody>
                                @php
                                if (isset($quote_log['quote_details']['version_id'])) {
                                    unset($quote_log['quote_details']['version_id']);
                                }
                                if (isset($quote_log['premium_json']['versionId'])) {
                                    unset($quote_log['premium_json']['versionId']);
                                }
                                if (empty($quote_log)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($quote_log);
                                }
                                @endphp
                            </tbody>
                        </table> 
                        </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Corporate Vehicle Quote Data&nbsp;&nbsp;<i class=" new fa fa-angle-right"
                        data-bs-toggle="collapse" data-bs-target="#corporate_vehicle"></i></h3>
                <div id="corporate_vehicle" class="collapse all">
                    <pre>
                        <table>
                            <tbody>
                                @php
                                if (isset($corporate_vehicles_quote_request['version_id'])) {
                                    unset($corporate_vehicles_quote_request['version_id']);
                                }
                                if (empty($corporate_vehicles_quote_request)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($corporate_vehicles_quote_request);
                                }
                                @endphp
                            </tbody>
                        </table> 
                        </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Proposal Data&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        data-bs-target="#proposal_data"></i></h3>
                <div id="proposal_data" class="collapse all">
                    <pre>
                        <table>
                            <tbody>
                                @php
                                if (empty($user_proposal)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($user_proposal);
                                }
                                @endphp
                            </tbody>
                        </table> 
                        </pre>
                </div>
                <br><br>
            </div>
            <div class="col-md-6">
                <h3>Agent Details&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse"
                        data-bs-target="#agent_details"></i></h3>
                @php
                # B2C Changes.
                if(empty($agent_details[0]->seller_type) && !empty($agent_details[0]->user_id))
                $agent_details[0]->seller_type = 'b2c';
                @endphp
                <div id="agent_details" class="collapse all">
                    <pre>
                        <table>
                            <tbody>
                                @php
                                if (empty($agent_details)) {
                                    echo '<td>'.'[]'.'</td>';
                                }
                                else {
                                    RecursiveTable($agent_details);
                                }
                                @endphp
                            </tbody>
                        </table> 
                        </pre>
                </div>
                <br><br>
            </div>
            <!-- premium details -->
            <div class="row">
                <div class="col-md-12">
                    <h3>Premium Details&nbsp;&nbsp;<i class=" new fa fa-angle-right"
                            data-bs-toggle="collapse" data-bs-target="#premium_details"></i></h3>
                    <div id="premium_details" class="collapse all">
                        <pre>
                            <table>
                                <tbody>
                                    @php
                                    if (empty($premium_details)) {
                                        echo '<td>'.'[]'.'</td>';
                                    }
                                    else {
                                        RecursiveTable($premium_details);
                                    }
                                    @endphp
                                </tbody>
                            </table> 
                            </pre>
                    </div>
                    <br><br>
                </div>
                @if(!empty($brokerCommission))
                <div class="col-md-12">
                    <h3>Commission Details&nbsp;&nbsp;<i class=" new fa fa-angle-right"
                            data-bs-toggle="collapse" data-bs-target="#Commission_Details"></i></h3>
                    <div id="Commission_Details" class="collapse all">
                        <pre>
                            <table>
                                <tbody>
                                    @php
                                    if (empty($brokerCommission)) {
                                        echo '<td>'.'[]'.'</td>';
                                    }
                                    else {
                                        RecursiveTable($brokerCommission);
                                    }
                                    @endphp
                                </tbody>
                            </table> 
                            </pre>
                    </div>
                    <br><br>
                </div>
                @endif
            </div>
            @include('premium-breakup')
        </div>
        @endif
    </div>
</div>
@push('scripts')
<script>
    $('#journey_data,#journey_Stage,#selected_addons,#policy_details,#break_in_details,#payment_response,#quote_data,#corporate_vehicle,#proposal_data,#agent_details,#premium_details,#premium_breakup,#broker_Commission').on('show.bs.collapse', function (event) {
        let id_name = $(event.target);
        let id = '#' + id_name.attr('id');
        collapse(id, 90, true);
        let allSections = $('.new');
        var  closeCount = openCount = 0
        allSections.each(function (index, value) {
            let transform = $(value).css('transform');
            if (transform.search('-1') !== -1) {;
                openCount++;
            } else {
                closeCount++;
            }
        });
       
        if(openCount == 1){
            $('#view_all').html("Show All");
        }
        else if(closeCount == 0){
            $('#view_all').html("Hide All");
          }
       })
    $('#journey_data,#journey_Stage,#selected_addons,#policy_details,#break_in_details,#payment_response,#quote_data,#corporate_vehicle,#proposal_data,#agent_details,#premium_details,#premium_breakup,#broker_Commission').on('hide.bs.collapse', function (event) {
        let id_name = $(event.target);
        let id = '#' + id_name.attr('id');
        collapse(id, 90, true);
        let allSections = $('.new');
        var  closeCount = openCount = 0
        allSections.each(function (index, value) {
            let transform = $(value).css('transform');
            if (transform.search('-1') !== -1) {;
                openCount++;
            } else {
                closeCount++;
            }
        });
        $(id).prev().children().css('transform', 'rotate(360deg)');
        if(closeCount == 0){
            $('#view_all').html("Show All");
            $(id).prev().children().css('transform', 'rotate(360deg)');
        }
        // else{
        //     $('#view_all').html("Hide All");

        // }
    })
       
    if($('.check').val())
    {
        $('#view_all').show();
    }
    else
    {
        $('#view_all').hide();
    }

    $('#view_all').click( function() {
        let allSections = $('.new');
        var  closeCount = openCount = 0
        allSections.each(function (index, value) {
            let transform = $(value).css('transform');
            let id = $(value).attr('data-bs-target');
            if (transform.search('-1') !== -1) {
                openCount++;
            } else {
                closeCount++;
            }
        });

        if (allSections.length == openCount) {
            allSections.each(function (index, value) {
                let id = $(value).attr('data-bs-target');
                collapse(id);
            });
            $('#view_all').html("Show All");
        } else {
            allSections.each(function (index, value) {
                let id = $(value).attr('data-bs-target');
                collapse(id, 90, true);
            });
            $('#view_all').html("Hide All");
        }
    });

    function collapse(id=null, degree = 360, show = false) {
        if (show) {
            $(id).prev().children().css('transform','rotate(' + degree + 'deg)');
            $(id).addClass('show');
        } else {
            $(id).prev().children().css('transform','rotate(' + degree + 'deg)');
            $(id).removeClass('show');
        }
    }     
</script>
@endpush
@endsection