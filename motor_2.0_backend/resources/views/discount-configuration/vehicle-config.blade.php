@extends('layout.app', ['activePage' => 'discount-configurations', 'titlePage' => __('Discount Configuration')])
@section('content')
<style>
    .nav-link.active {
        background: #1F3BB3 !important;
        color: #fff !important;
    }
</style>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Discount Configuration</h4>
                    <div class="row">
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
                            @include('discount-configuration.tabs')
                            <div class="tab-content" id="">
                                <div class="tab-pane fade show active" id="nav-vehicle-config" role="tabpanel" aria-labelledby="nav-vehicle-config-tab">
                                    <form name="vehicle-config-form" action="" method="post">
                                        @csrf
                                        <div class="row">
                                            <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                                <label for="">Car : </label>
                                            </div>
                                            <div class="form-group col-md-6 col-lg-4 col-xl-3 d-flex flex-row align-items-center">
                                                <input type="number" name="carDiscount" id="" class="form-control" required value="{{old('carDiscount') ?? $vehicleDiscount['car']}}">
                                                <span style="margin-left:10px">%</span>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                                <label for="">Bike : </label>
                                            </div>
                                            <div class="form-group col-md-6 col-lg-4 col-xl-3 d-flex flex-row align-items-center">
                                                <input type="number" name="bikeDiscount" id="" class="form-control" required value="{{old('bikeDiscount') ?? $vehicleDiscount['bike']}}">
                                                <span style="margin-left:10px">%</span>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                                <label for="">PCV : </label>
                                            </div>
                                            <div class="form-group col-md-6 col-lg-4 col-xl-3 d-flex flex-row align-items-center">
                                                <input type="number" name="pcvDiscount" id="" class="form-control" required value="{{old('pcvDiscount') ?? $vehicleDiscount['pcv']}}">
                                                <span style="margin-left:10px">%</span>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                                <label for="">GCV : </label>
                                            </div>
                                            <div class="form-group col-md-6 col-lg-4 col-xl-3 d-flex flex-row align-items-center">
                                                <input type="number" name="gcvDiscount" id="" class="form-control" required value="{{old('gcvDiscount') ?? $vehicleDiscount['gcv']}}">
                                                <span style="margin-left:10px">%</span>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
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
</div>
@endsection
