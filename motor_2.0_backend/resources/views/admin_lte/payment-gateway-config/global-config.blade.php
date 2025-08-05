@extends('admin_lte.layout.app', ['activePage' => 'payment-gateway-global-configuration', 'titlePage' => __('Payment Gateway GLobal Configuration')])
@section('content')
<style>
    .nav-link.active {
        background: #1F3BB3 !important;
        color: #fff !important;
    }
</style>
<!-- <div class="content-wrapper"> -->
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <!-- <h4 class="card-title">Payment Gateway Configuration</h4> -->
                    <div class="row">
                    <br/>
                        <div class="col-md-6 col-lg-4">
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
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            @include('admin.payment-gateway-config.tabs')
                            <div class="tab-content" id="">
                                <div class="tab-pane fade active show" id="nav-setting" role="tabpanel" aria-labelledby="nav-setting-tab">
                                    <form action="" method="post">
                                        @csrf
                                        <div class="row">
                                            <div class="form-group col-md-6 col-lg-3 col-xl-2">
                                                <label for="">Select the Payment Gateway : </label>
                                            </div>
                                            <div class="form-group col-12 col-md-6 col-lg-4 col-xl-3">
                                                <select required id="paymentGateway" name="paymentGateway" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" name="configType">
                                                    <option value="" selected disabled>Please select</option>
                                                    @foreach ($gatewayList as $key => $g)
                                                    <option value="{{$key}}">{{$g}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row field-section d-none">
                                        </div>
                                        
                                        <div class="row form-btn-section d-none">
                                            <div class="col-12">
                                                <button class="btn btn-primary">Save</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- </div> -->

<script>
    const getFieldsUrl = "<?php echo route('admin.pg-config.get-fields'); ?>"
</script>
<script src="{{asset('admin1/js/payment-gateway-config/global-config.js')}}"></script>
@endsection
