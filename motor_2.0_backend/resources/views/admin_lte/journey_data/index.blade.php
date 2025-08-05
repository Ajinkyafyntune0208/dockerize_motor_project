@extends('admin_lte.layout.app', ['activePage' => 'log-third-paty-payment', 'titlePage' => __('Third Party Payment Logs')])
@section('content')
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
    <div class="row">
        <!-- left column -->
        <div class="col-md-12">
            <!-- general form elements -->
            <div class="card card-primary">
                <!-- /.card-header -->
                <!-- form start -->
                <div class="card-body">
                    <form action="" method="GET" name="serverErrorForm">
                        <div class="col-8 col-md-10 text-center">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label>Enquiry Id</label>
                                        <input type="text" name="enquiry_id" value="{{ old('enquiry_id', request()->enquiry_id) }}" required class="form-control check" placeholder="Enquiry ID" autocomplete="off">
                                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Submit</button>
                                </div>
                            </div>
                    </form>
                </div>
            </div>
            <!-- /.card -->
        </div>
        <!--/.col (left) -->

    </div>
</div>
@if (!empty($journey_data))
<div class="row">
    <div class="col-md-6">
        <h3>Journey Data&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" for="btnControl" data-bs-target="#journey_data"></i></h3>
        @php
        # B2C Changes.
        if(empty($journey_data->agent_details[0]->seller_type) &&
        !empty($journey_data->agent_details[0]->user_id))
        $journey_data->agent_details[0]->seller_type = 'b2c';
        @endphp
        <div id="journey_data" class="collapse all">
            <pre>{{ json_encode($journey_data->getattributes()?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ?? '' }}</pre>
            <span style="font-size: 13px; font-weight:550">Smsotps</span>
            <pre>{{ json_encode($journey_data->smsOtps?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ?? '' }}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Journey Stage&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#journey_Stage"></i></h3>
        <div id="journey_Stage" class="collapse all">
            <pre>{!! json_encode($journey_stage, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Selected Addons&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#selected_addons"></i></h3>
        <div id="selected_addons" class="collapse all">
            <pre>{!! json_encode($addons ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) !!}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Policy Details&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#policy_details"></i></h3>
        <div id="policy_details" class="collapse all">
            <pre>{!! json_encode($policy_details ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) !!}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Break-in Details&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#break_in_details"></i></h3>
        <div id="break_in_details" class="collapse all">
            <pre>{!! json_encode($breakin_status ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) !!}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Payment Response&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#payment_response"></i></h3>
        <div id="payment_response" class="collapse all">
            <pre>{!! json_encode($payment_response ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) !!}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Quote Data&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#quote_data"></i></h3>
        <div id="quote_data" class="collapse all">
            <pre>{!! json_encode($quote_log ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) !!}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Corporate Vehicle Quote Data&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#corporate_vehicle"></i></h3>
        <div id="corporate_vehicle" class="collapse all">
            <pre>{!! json_encode($corporate_vehicles_quote_request ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) !!}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Proposal Data&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#proposal_data"></i></h3>
        <div id="proposal_data" class="collapse all">
            <pre>{!! json_encode($user_proposal ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) !!}</pre>
        </div>
        <br><br>
    </div>
    <div class="col-md-6">
        <h3>Agent Details&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#agent_details"></i></h3>
        @php
        # B2C Changes.
        if(empty($agent_details[0]->seller_type) && !empty($agent_details[0]->user_id))
        $agent_details[0]->seller_type = 'b2c';
        @endphp
        <div id="agent_details" class="collapse all">
            <pre>{!! json_encode($agent_details?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) !!}</pre>
        </div>
        <br><br>
    </div>
    <!-- premium details -->
    <div class="row">
        <div class="col-md-12">
            <h3>Premium Details&nbsp;&nbsp;<i class=" new fa fa-angle-right" data-bs-toggle="collapse" data-bs-target="#premium_details"></i></h3>
            <div id="premium_details" class="collapse all">
                <pre>{!! json_encode($premium_details ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}</pre>
            </div>
            <br><br>
        </div>
    </div>
    @include('premium-breakup')
</div>
@endif
@endsection('content')

@push('scripts')
<script>
    $('#journey_data,#journey_Stage,#selected_addons,#policy_details,#break_in_details,#payment_response,#quote_data,#corporate_vehicle,#proposal_data,#agent_details,#premium_details,#premium_breakup').on('show.bs.collapse', function(event) {
        let id_name = $(event.target);
        let id = '#' + id_name.attr('id');
        collapse(id, 90, true);
        let allSections = $('.new');
        var closeCount = openCount = 0
        allSections.each(function(index, value) {
            let transform = $(value).css('transform');
            if (transform.search('-1') !== -1) {
                ;
                openCount++;
            } else {
                closeCount++;
            }
        });

        if (openCount == 1) {
            $('#view_all').html("Show All");
        } else if (closeCount == 0) {
            $('#view_all').html("Hide All");
        }
    })
    $('#journey_data,#journey_Stage,#selected_addons,#policy_details,#break_in_details,#payment_response,#quote_data,#corporate_vehicle,#proposal_data,#agent_details,#premium_details,#premium_breakup').on('hide.bs.collapse', function(event) {
        let id_name = $(event.target);
        let id = '#' + id_name.attr('id');
        collapse(id, 90, true);
        let allSections = $('.new');
        var closeCount = openCount = 0
        allSections.each(function(index, value) {
            let transform = $(value).css('transform');
            if (transform.search('-1') !== -1) {
                ;
                openCount++;
            } else {
                closeCount++;
            }
        });
        $(id).prev().children().css('transform', 'rotate(360deg)');
        if (closeCount == 0) {
            $('#view_all').html("Show All");
            $(id).prev().children().css('transform', 'rotate(360deg)');
        }
        // else{
        //     $('#view_all').html("Hide All");

        // }
    })

    if ($('.check').val()) {
        $('#view_all').show();
    } else {
        $('#view_all').hide();
    }

    $('#view_all').click(function() {
        let allSections = $('.new');
        var closeCount = openCount = 0
        allSections.each(function(index, value) {
            let transform = $(value).css('transform');
            let id = $(value).attr('data-bs-target');
            if (transform.search('-1') !== -1) {
                openCount++;
            } else {
                closeCount++;
            }
        });

        if (allSections.length == openCount) {
            allSections.each(function(index, value) {
                let id = $(value).attr('data-bs-target');
                collapse(id);
            });
            $('#view_all').html("Show All");
        } else {
            allSections.each(function(index, value) {
                let id = $(value).attr('data-bs-target');
                collapse(id, 90, true);
            });
            $('#view_all').html("Hide All");
        }
    });

    function collapse(id = null, degree = 360, show = false) {
        if (show) {
            $(id).prev().children().css('transform', 'rotate(' + degree + 'deg)');
            $(id).addClass('show');
        } else {
            $(id).prev().children().css('transform', 'rotate(' + degree + 'deg)');
            $(id).removeClass('show');
        }
    }
</script>
@endpush