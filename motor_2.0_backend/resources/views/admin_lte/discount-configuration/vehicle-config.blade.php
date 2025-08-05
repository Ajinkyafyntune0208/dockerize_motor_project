@extends('admin_lte.layout.app', ['activePage' => 'discount-configurations', 'titlePage' => __('Discount Configuration')])
@section('content')
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
<div class="card"> 
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                @include('admin_lte.discount-configuration.tabs')
                <div class="tab-content" id="">
                    <div class="tab-pane fade show active" id="nav-vehicle-config" role="tabpanel" aria-labelledby="nav-vehicle-config-tab">
                        <form name="vehicle-config-form" action="" method="post">
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                    <label for="">Car <span class="text-danger"> * </span>: </label>
                                </div>
                                <div class="form-group col-md-6 col-lg-4 col-xl-3 d-flex flex-row align-items-center">
                                    <input type="number" name="carDiscount" id="" class="form-control" required value="{{old('carDiscount') ?? $vehicleDiscount['car']}}">
                                    <span style="margin-left:10px">%</span>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                    <label for="">Bike <span class="text-danger"> * </span>: </label>
                                </div>
                                <div class="form-group col-md-6 col-lg-4 col-xl-3 d-flex flex-row align-items-center">
                                    <input type="number" name="bikeDiscount" id="" class="form-control" required value="{{old('bikeDiscount') ?? $vehicleDiscount['bike']}}">
                                    <span style="margin-left:10px">%</span>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                    <label for="">PCV <span class="text-danger"> * </span>: </label>
                                </div>
                                <div class="form-group col-md-6 col-lg-4 col-xl-3 d-flex flex-row align-items-center">
                                    <input type="number" name="pcvDiscount" id="" class="form-control" required value="{{old('pcvDiscount') ?? $vehicleDiscount['pcv']}}">
                                    <span style="margin-left:10px">%</span>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                    <label for="">GCV <span class="text-danger"> * </span>: </label>
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
@endsection
