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
                    <div class="tab-pane fade show active" id="nav-ic-config" role="tabpanel" aria-labelledby="nav-ic-config-tab">
                        <div class="mt-5 justify-content-end">
                            <div class="col-12 d-flex justify-content-between mb-2">
                                <h5>Car</h5>
                            </div>
                            <form action="" method="post">
                                @csrf
                                <input type="hidden" name="type" value="car">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered .table-sm">
                                            <thead>
                                                <th>Insurance Company Name</th>
                                                <th>Discount (%)</th>
                                            </thead>
                                            <tbody>
                                                @foreach ($applicableIcs as $ic)
                                                <tr>
                                                    <td>{{$ic->company_name}}</td>
                                                    <td>
                                                        <input type="text" class="form-control" pattern="^(0\.[1-9]\d?|[1-9]\d?(\.\d{1,2})?|100(\.0{1,2})?)$" title="Please enter a valid discount rate" name="carDiscount[{{$ic->company_id}}]" required value="{{old('carDiscount')[$ic->company_id] ?? $discounts['car'][$ic->company_id] ?? ''}}">
                                                    </td>
                                                </tr>
                                                @endforeach
                                                <tr>
                                                    <td colspan="2" align="right">
                                                        <button type="submit" class="btn btn-primary">Save</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <div class="mt-5 justify-content-end">
                            <div class="col-12 d-flex justify-content-between mb-2">
                                <h5>Bike</h5>
                            </div>
                            <form action="" method="post">
                                @csrf
                                <input type="hidden" name="type" value="bike">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered .table-sm">
                                            <thead>
                                                <th>Insurance Company Name</th>
                                                <th>Discount (%)</th>
                                            </thead>
                                            <tbody>
                                                @foreach ($applicableIcs as $ic)
                                                <tr>
                                                    <td>{{$ic->company_name}}</td>
                                                    <td>
                                                        <input type="text" class="form-control" pattern="^(0\.[1-9]\d?|[1-9]\d?(\.\d{1,2})?|100(\.0{1,2})?)$" title="Please enter a valid discount rate" required name="bikeDiscount[{{$ic->company_id}}]" value="{{old('bikeDiscount')[$ic->company_id] ?? $discounts['bike'][$ic->company_id] ?? ''}}">
                                                    </td>
                                                </tr>
                                                @endforeach
                                                <tr>
                                                    <td colspan="2" align="right">
                                                        <button type="submit" class="btn btn-primary">Save</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        
                        <div class="mt-5 justify-content-end">
                            <div class="col-12 d-flex justify-content-between mb-2">
                                <h5>PCV</h5>
                            </div>
                            <form action="" method="post">
                                @csrf
                                <input type="hidden" name="type" value="pcv">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered .table-sm">
                                            <thead>
                                                <th>Insurance Company Name</th>
                                                <th>Discount (%)</th>
                                            </thead>
                                            <tbody>
                                                @foreach ($applicableIcs as $ic)
                                                <tr>
                                                    <td>{{$ic->company_name}}</td>
                                                    <td>
                                                        <input type="text" pattern="^(0\.[1-9]\d?|[1-9]\d?(\.\d{1,2})?|100(\.0{1,2})?)$" title="Please enter a valid discount rate" class="form-control" name="pcvDiscount[{{$ic->company_id}}]" required value="{{old('pcvDiscount')[$ic->company_id] ?? $discounts['pcv'][$ic->company_id] ?? ''}}">
                                                    </td>
                                                </tr>
                                                @endforeach
                                                <tr>
                                                    <td colspan="2" align="right">
                                                        <button type="submit" class="btn btn-primary">Save</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        
                        <div class="mt-5 justify-content-end">
                            <div class="col-12 d-flex justify-content-between mb-2">
                                <h5>GCV</h5>
                            </div>
                            <form action="" method="post">
                                @csrf
                                <input type="hidden" name="type" value="gcv">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered .table-sm">
                                            <thead>
                                                <th>Insurance Company Name</th>
                                                <th>Discount (%)</th>
                                            </thead>
                                            <tbody>
                                                @foreach ($applicableIcs as $ic)
                                                <tr>
                                                    <td>{{$ic->company_name}}</td>
                                                    <td>
                                                        <input type="text" class="form-control" pattern="^(0\.[1-9]\d?|[1-9]\d?(\.\d{1,2})?|100(\.0{1,2})?)$" title="Please enter a valid discount rate" required name="gcvDiscount[{{$ic->company_id}}]" value="{{old('gcvDiscount')[$ic->company_id] ?? $discounts['gcv'][$ic->company_id] ?? ''}}">
                                                    </td>
                                                </tr>
                                                @endforeach
                                                <tr>
                                                    <td colspan="2" align="right">
                                                        <button type="submit" class="btn btn-primary">Save</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
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
@endsection
