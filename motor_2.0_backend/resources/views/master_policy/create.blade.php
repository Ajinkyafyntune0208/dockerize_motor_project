@extends('layout.app', ['activePage' => 'master-product', 'titlePage' => __('Master Policy')])
@section('content')
<style>
    :root{
    --primary: #1f3bb3;
    --warn: #ffd000;
    }
    .btn-cus{
        height: 40px;
        max-width: 150px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-cus:hover{
        background-color: var(--primary);
        color: white;
    }
    .btn-cus-warning{
        background-color: var(--warn);
        border: 1px solid var(--warn);
        color: white;
    }
    .btn-cus-warning:hover{
        background: transparent;
        color: black;
    }
    .btn-cus.active{
        background-color: var(--primary);
        color: white;
    }
    .btn-cus-v2{
        background-color: rgb(245, 245, 245);
        color: var(--primary);
        border: 1px solid black;
        border-radius: 10px;
    }
    .btn-cus-v2:hover , .btn-cus-v2:focus{
        background: #cacaca;
        color: rgb(0, 0, 0) !important;
        outline: none;
    }
    .filter-option-inner-inner{
        color: rgb(0, 0, 0) !important;
    }
  
</style>

    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Master Product</h4>

                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.master-product.store') }}" method="POST" class="mt-3"
                            name="add_master">
                            @csrf @method('POST')
                            <div class="row mb-3">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Product Name <span style="color: red;">*</span> </label>
                                        <input id="product_name" name="product_name" type="text" class="form-control"
                                            placeholder="Product Name" value="{{ old('product_name') }}">
                                        @error('product_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Product Identifier<span style="color: red;">*</span></label>
                                        <input id="product_identifier" name="product_identifier" type="text"
                                            class="form-control" placeholder="Product Identifier"
                                            value="{{ old('product_identifier') }}">
                                        @error('product_identifier')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Policy Type<span style="color: red;">*</span></label>
                                        <input id="policy_type" name="policy_type" type="text" class="form-control"
                                            placeholder="value" value="{{ old('policy_type') }}">
                                        @error('policy_type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>GCV Carrier Type</label>
                                        <select id="gcv_carrier_type" name="gcv_carrier_type" data-style="btn-cus-v2"
                                            class="selectpicker w-100" data-live-search="true">
                                            <option value="">Nothing selected</option>
                                            <option {{ old('gcv_carrier_type') == 'PUBLIC' ? 'selected' : '' }} value="{{ old('gcv_carrier_type') ? old('gcv_carrier_type') : 'PUBLIC' }}">
                                                    Public</option>
                                            <option {{ old('gcv_carrier_type') == 'PRIVATE' ? 'selected' : '' }} value="{{ old('gcv_carrier_type') ? old('gcv_carrier_type') : 'PRIVATE' }}">
                                                    Private</option>
                                        </select>
                                        @error('gcv_carrier_type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label class="active" for="label">Default Discount</label>
                                        <input id="default_discount" name="default_discount" type="text"
                                            class="form-control" placeholder="Value" value="{{ old('default_discount') }}">
                                        @error('default_discount')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Product Sub Type<span style="color: red;">*</span></label>
                                        <select id="product_sub_type" name="product_sub_type" data-style="btn-cus-v2"
                                            class="selectpicker w-100" data-live-search="true">
                                            <option value="">Nothing selected</option>
                                            @foreach ($master_product_sub_types as $key => $master_product_sub_type)
                                                <option
                                                    {{ $master_product_sub_type->product_sub_type_id == old('product_sub_type') ? 'selected' : '' }}
                                                    value="{{ $master_product_sub_type->product_sub_type_id }}">
                                                    {{ $master_product_sub_type->product_sub_type_code }}</option>
                                            @endforeach
                                        </select>
                                        @error('product_sub_type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Company Name<span style="color: red;">*</span></label>
                                        <select id="company_name" name="company_name" data-style="btn-cus-v2"
                                            class="selectpicker w-100" data-live-search="true">
                                            <option value="">Nothing selected</option>
                                            @foreach ($master_companies as $key => $master_company)
                                                <option
                                                    {{ $master_company->company_id == old('company_name') ? 'selected' : '' }}
                                                    value="{{ $master_company->company_id }}">
                                                    {{ $master_company->company_name }}</option>
                                            @endforeach
                                        </select>
                                        @error('company_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Premium Type<span style="color: red;">*</span></label>
                                        <select id="premium_type" name="premium_type" data-style="btn-cus-v2"
                                            class="selectpicker w-100" data-live-search="true">
                                            <option value="">Nothing selected</option>
                                            @foreach ($master_premiums as $key => $master_premium)
                                                <option {{ $master_premium->id == old('premium_type') ? 'selected' : '' }}
                                                    value="{{ $master_premium->id }}">{{ $master_premium->premium_type }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('premium_type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Business Type<span style="color: red;">*</span></label>
                                        @php
                                            $business_types = [
                                                'newbusiness' => 'New business',
                                                'rollover' => 'Rollover',
                                                'breakin' => 'Breakin',
                                            ];
                                        @endphp
                                        <select name="business_type[]" id="business_type" data-style="btn-cus-v2"
                                            class="selectpicker w-100" data-live-search="true" data-actions-box="true" multiple>
                                            @foreach($business_types as $key => $value) 
                                            <option {{ (in_array($key, old('business_type') ?? []) ? "selected" : "") }} value="{{ $key }}">{{ $value }}</option>
                                                @endforeach
                                        </select>
                                        @error('business_type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Is Premium Online<span style="color: red;">*</span></label>
                                        <select name="premium_online" id="premium_online" data-style="btn-cus-v2"
                                            class="selectpicker w-100">
                                            <option value="">Nothing selected</option>
                                            <option {{ old('premium_online') == 'Yes' ? 'selected' : 'Yes' }}
                                                value="Yes">Yes</option>
                                            <option {{ old('premium_online') == 'No' ? 'selected' : 'Yes' }} value="No">
                                                No</option>
                                        </select>
                                        @error('premium_online')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label class="active" for="label">Is Proposal Online<span style="color: red;">*</span></label>
                                        <select name="proposal_online" id="proposal_online" data-style="btn-cus-v2"
                                            class="selectpicker w-100">
                                            <option value="">Nothing selected</option>
                                            <option {{ old('proposal_online') == 'Yes' ? 'selected' : 'Yes' }}
                                                value="Yes">Yes</option>
                                            <option {{ old('proposal_online') == 'No' ? 'selected' : 'Yes' }}
                                                value="No">No</option>
                                        </select>
                                        @error('proposal_online')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Is Payment Online<span style="color: red;">*</span></label>
                                        <select name="payment_online" id="payment_online" data-style="btn-cus-v2"
                                            class="selectpicker w-100">
                                            <option value="">Nothing selected</option>
                                            <option {{ old('payment_online') == 'Yes' ? 'selected' : 'Yes' }}
                                                value="Yes">Yes</option>
                                            <option {{ old('payment_online') == 'No' ? 'selected' : 'Yes' }}
                                                value="No">No</option>
                                        </select>
                                        @error('payment_online')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Driver Discount<span style="color: red;">*</span></label>
                                        <select name="driver_discount" id="driver_discount" data-style="btn-cus-v2"
                                            class="selectpicker w-100">
                                            <option value="">Nothing selected</option>
                                            <option {{ old('driver_discount') == 'Yes' ? 'selected' : 'Yes' }}
                                                value="Yes">Yes</option>
                                            <option {{ old('driver_discount') == 'No' ? 'selected' : 'Yes' }}
                                                value="No">No</option>
                                        </select>
                                        @error('driver_discount')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Pos Flag<span style="color: red;">*</span></label>
                                        @php
                                        $pos_flags = [
                                            'P' => 'P - POS',
                                            'N' => 'N - NONPOS',
                                            'EV' => 'EV - ELECTRIC VEHICLE',
                                            'A'=>'A - ESSONE',
                                            'D'=>'D - Driver App',
                                            'E'=>'E - Employee',
                                            'M'=>'M - MISP'
                                        ];
                                        $owner_type = [
                                            'I' => 'I - Individual',
                                            'C' => 'C - Company',
                                        ]
                                    @endphp
                                        <select name="pos_flag[]" id="pos_flag" data-style="btn-cus-v2"
                                        data-live-search="true" data-actions-box="true" multiple
                                            class="selectpicker w-100">
                                            @foreach($pos_flags as $key => $value)  
                                            <option {{ (in_array($key, old('pos_flag') ?? []) ? "selected" : "") }} value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                        @error('pos_flag')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label class="active" for="label">Owner Type<span style="color: red;">*</span></label>
                                        <select name="owner_type[]" id="owner_type" data-style="btn-cus-v2"
                                            data-live-search="true" data-actions-box="true" multiple
                                            class="selectpicker w-100">
                                            @foreach($owner_type as $key => $value)  
                                            <option {{ (in_array($key, old('owner_type') ?? []) ? "selected" : "") }} value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                        @error('owner_type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label class="active" for="label">Zero Dep.<span style="color: red;">*</span></label>
                                        <select name="zero_dep" id="zero_dep" data-style="btn-cus-v2"
                                            class="selectpicker w-100">
                                            <option value="">Nothing selected</option>
                                            <option {{ old('zero_dep') == 'NA' || old('zero_dep') == '1' ? 'selected' : '' }} value="1">No</option>
                                            <option {{ old('zero_dep') == '0' ? 'selected' : '' }} value="0">Yes</option>
                                        </select>
                                        @error('zero_dep')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Status<span style="color: red;">*</span></label>
                                        <select name="status" id="status" data-style="btn-cus-v2"
                                            class="selectpicker w-100">
                                            <option value="">Nothing selected</option>
                                            <option {{ old('status') == 'Active' ? 'selected' : '' }} value="Active">
                                                Active</option>
                                            <option {{ old('status') == 'Inactive' ? 'selected' : '' }} value="Inactive">
                                                Inactive</option>
                                        </select>
                                        @error('status')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                @if (!empty($previous_url))
                                    <input type="hidden" name="prev" value="{{$previous_url}}">
                                @endif
                                <div class="col-12 d-flex justify-content-center mt-3">
                                    <div class="mx-3">
                                        <button type="submit" class="btn btn-cus">Submit</button>
                                    </div>
                                    <div class="mx-3">
                                        @if (!empty($previous_url))
                                            <a href="{{$previous_url}}" class="btn btn-cus btn-cus-warning">Back</a>
                                        @else
                                            <a href="{{route('admin.master-product.index')}}" class="btn btn-cus btn-cus-warning">Back</a>
                                        @endif
                                    </div>
                                </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
