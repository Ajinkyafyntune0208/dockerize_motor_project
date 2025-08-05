@extends('admin_lte.layout.app', ['activePage' => 'master-product', 'titlePage' => __('Master Policy')])
@section('content')
<a  href="{{ route('admin.master-product.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <div class="card-body">
        <form action="{{ route('admin.master-product.update', $master_policy) }}"
            method="POST" class="mt-3" name="edit_master">
            @csrf @method('PUT')
            <div class="row mb-3">
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Product Name<span style="color: red;">*</span></label>
                        <input id="product_name" name="product_name" type="text" class="form-control"
                            placeholder="Product Name" value=" {{ old('product_name') ? old('product_name') : (empty($master_product->product_name) ? '' : $master_product->product_name) }}">
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
                            value="{{ old('product_identifier') ? old('product_identifier') : (empty($master_product->product_identifier) ? '' : $master_product->product_identifier) }}">
                        @error('product_identifier')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Policy Type<span style="color: red;">*</span></label>
                        <input id="policy_type" name="policy_type" type="text" class="form-control"
                            placeholder="value" value="{{ old('policy_type') ? old('policy_type') : (empty($master_policy->policy_type) ? '' : $master_policy->policy_type) }}">
                        @error('policy_type')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>GCV Carrier Type</label>
                        <select id="gcv_carrier_type" name="gcv_carrier_type" class="select2 w-100 form-control" data-live-search="true">
                            <option value="">Nothing selected</option>
                            <option {{ old('gcv_carrier_type') || $master_policy->gcv_carrier_type == 'PUBLIC' ? 'selected' : '' }} value='PUBLIC'>
                                    Public</option>
                            <option {{ old('gcv_carrier_type') || $master_policy->gcv_carrier_type == 'PRIVATE' ? 'selected' : '' }} value='PRIVATE'>
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
                            class="form-control" placeholder="Value"
                            value="{{ old('default_discount') ? old('default_discount') : (empty($master_policy->default_discount) ? '' : $master_policy->default_discount) }}">
                        @error('default_discount')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Product Sub Type<span style="color: red;">*</span></label>
                        <select id="product_sub_type" name="product_sub_type" class="select2 w-100 form-control" data-live-search="true">
                            <option value="">Nothing selected</option>
                            @foreach ($master_product_sub_types as $key => $master_product_sub_type)
                            @if (old('product_sub_type'))
                            <option @if (old('product_sub_type') == $master_product_sub_type->product_sub_type_id) {{ 'selected' }} @endif
                            value="{{ $master_product_sub_type->product_sub_type_id }}">{{ $master_product_sub_type->product_sub_type_code }}</option>
                            @else
                            <option {{ $master_product_sub_type->product_sub_type_id == $master_policy->product_sub_type_id ? 'selected' : '' }}
                        value="{{ $master_product_sub_type->product_sub_type_id }}">
                        {{ $master_product_sub_type->product_sub_type_code }}</option>
                        @endif
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
                        <select id="company_name" name="company_name" class="select2 w-100 form-control" data-live-search="true">
                            <option value="">Nothing selected</option>
                            @foreach ($master_companies as $key => $master_company)
                            @if(old('company_name'))
                            <option @if (old('company_name') == $master_company->company_id) {{ 'selected' }} @endif
                                value="{{ $master_company->company_id }}">{{ $master_company->company_name }}</option>
                            @else
                                <option
                                    {{$master_company->company_id == $master_policy->insurance_company_id ? 'selected' : '' }}
                                    value="{{ $master_company->company_id }}">
                                    {{ $master_company->company_name }}</option>
                                    @endif
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
                        <select id="premium_type" name="premium_type" class="select2 w-100 form-control" data-live-search="true">
                            <option value="">Nothing selected</option>
                            @foreach ($master_premiums as $key => $master_premium)
                            @if(old('premium_type'))
                            <option @if (old('premium_type') == $master_premium->id) {{ 'selected' }} @endif
                                value="{{ $master_premium->id }}">{{ $master_premium->premium_type }}
                            @else
                                <option
                                    {{ $master_premium->id == $master_policy->premium_type_id ? 'selected' : '' }}
                                    value="{{ $master_premium->id }}">{{ $master_premium->premium_type }}
                                </option>
                                @endif
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
                            $master_policy_business_types = explode(',', $master_policy->business_type);
                            $mp_owner_type = explode(',', $master_policy->owner_type);
                            $mp_pos_flag = explode(',', $master_policy->pos_flag);
                            $business_types = [
                                'newbusiness' => 'New business',
                                'rollover' => 'Rollover',
                                'breakin' => 'Breakin',
                            ];
                        @endphp
                        <select name="business_type[]" id="business_type" class="select2 w-100"
                        multiple="multiple">
                            @foreach($business_types as $key => $value) 
                            @if(old('business_type')) 
                            <option {{ (in_array($key, old('business_type') ?? []) ? "selected" : "") }} value="{{ $key }}">{{ $value }}</option>
                            @else
                                <option {{ (in_array($key, $master_policy_business_types) ? "selected" : "") }} value="{{ $key }}">{{ $value }}</option>
                            @endif
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
                        <select name="premium_online" id="premium_online" class="select2 w-100 form-control">
                            <option value="">Nothing selected</option>
                            @if(old('premium_online'))
                            <option @if (old('premium_online') == "Yes") {{ 'selected' }} @endif
                            value="Yes">Yes</option>
                            <option @if (old('premium_online') == "No") {{ 'selected' }} @endif
                            value="No">No</option>
                            @else
                            <option {{ $master_policy->is_premium_online == 'Yes' ? 'selected' : '' }}
                                value="Yes">Yes</option>
                            <option {{ $master_policy->is_premium_online == 'No' ? 'selected' : '' }}
                                value="No">
                                No</option>
                                @endif
                        </select>
                        @error('premium_online')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label class="active" for="label">Is Proposal Online<span style="color: red;">*</span></label>
                        <select name="proposal_online" id="proposal_online" class="select2 w-100 form-control">
                            <option value="">Nothing selected</option>
                            @if(old('proposal_online'))
                            <option @if (old('proposal_online') == "Yes") {{ 'selected' }} @endif
                            value="Yes">Yes</option>
                            <option @if (old('proposal_online') == "No") {{ 'selected' }} @endif
                            value="No">No</option>
                            @else
                            <option {{ $master_policy->is_proposal_online == 'Yes' ? 'selected' : 'Yes' }}
                                value="Yes">Yes</option>
                            <option {{ $master_policy->is_proposal_online == 'No' ? 'selected' : 'Yes' }}
                                value="No">No</option>
                                @endif
                        </select>
                        @error('proposal_online')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Is Payment Online<span style="color: red;">*</span></label>
                        <select name="payment_online" id="payment_online" class="select2 w-100 form-control">
                            <option value="">Nothing selected</option>
                            @if(old('payment_online'))
                            <option @if (old('payment_online') == "Yes") {{ 'selected' }} @endif
                            value="Yes">Yes</option>
                            <option @if (old('payment_online') == "No") {{ 'selected' }} @endif
                            value="No">No</option>
                            @else
                            <option {{ $master_policy->is_payment_online == 'Yes' ? 'selected' : 'Yes' }}
                                value="Yes">Yes</option>
                            <option {{ $master_policy->is_payment_online == 'No' ? 'selected' : 'Yes' }}
                                value="No">No</option>
                                @endif
                        </select>
                        @error('payment_online')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Driver Discount<span style="color: red;">*</span></label>
                        <select name="driver_discount" id="driver_discount" class="select2 w-100 form-control">
                            <option value="">Nothing selected</option>
                            @if(old('driver_discount'))
                            <option
                            @if (old('driver_discount') == "Yes") {{ 'selected' }} @endif
                            value="Yes">Yes</option>
                            <option
                            @if (old('driver_discount') == "No") {{ 'selected' }} @endif   
                            value="No">No</option>                                         value="No">No</option>
                            @else
                            <option
                                {{ $master_policy->good_driver_discount == 'Yes' ? 'selected' : '' }}
                                value="Yes">Yes</option>
                            <option {{ $master_policy->good_driver_discount == 'No' ? 'selected' : '' }}
                                value="No">No</option>
                                @endif
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
                        <select name="pos_flag[]" id="pos_flag" data-live-search="true" data-actions-box="true" multiple
                            class="select2 w-100 form-control">
                            @foreach($pos_flags as $key => $value) 
                            @if(old('pos_flag')) 
                            <option {{ (in_array($key, old('pos_flag') ?? []) ? "selected" : "") }} value="{{ $key }}">{{ $value }}</option>
                            @else
                            <option {{ (in_array($key,$mp_pos_flag) ? "selected" : "") }} value="{{ $key }}">{{ $value }}</option>
                            @endif
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
                        <select name="owner_type[]" id="owner_type" data-live-search="true" data-actions-box="true" multiple
                            class="select2 w-100 form-control">
                            @foreach($owner_type as $k => $val)  
                            <option {{ (in_array($k, $mp_owner_type) ? "selected" : "") }} value="{{ $k }}">{{ $val }}</option>
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
                        <select name="zero_dep" id="zero_dep" class="select2 w-100 form-control">
                            <option value="">Nothing selected</option>
                        @if (old('zero_dep') !='' || old('zero_dep') != null) 
                            <option {{ old('zero_dep') == 'NA' || old('zero_dep') == '1' ? 'selected' : '' }} value="1">No</option>
                            <option {{ old('zero_dep') == '0' ? 'selected' : '' }} value="0">Yes</option>
                        @else 
                            <option {{ $master_policy->zero_dep == 'NA' || $master_policy->zero_dep == '1' ? 'selected' : '' }} value="1">No</option>
                            <option {{ $master_policy->zero_dep == '0' ? 'selected' : '' }} value="0">Yes</option>
                        @endif
                        </select>
                        @error('zero_dep')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Status<span style="color: red;">*</span></label>
                        <select name="status" id="status" class="select2 w-100 form-control">
                            <option value="">Nothing selected</option>
                                @if (old('status'))
                                <option @if (old('status') == "Active") {{ 'selected' }} @endif
                                value="Active">
                                Active</option>
                                <option @if (old('status') == "Inactive") {{ 'selected' }} @endif
                                value="Inactive">
                                Inactive</option>
                                @else
                                <option {{ $master_policy->status == 'Active' ? 'selected' : '' }}
                                    
                                    value="Active">
                                    Active</option>
                                <option {{ $master_policy->status == 'Inactive' ? 'selected' : '' }}
                                    
                                    value="Inactive">
                                    Inactive</option>
                                    @endif
                        </select>
                        @error('status')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                @if (!empty($previous_url))
                    <input type="hidden" name="prev" value="{{$previous_url}}">
                @endif
                <div class="col-12 d-flex mt-3">
                    <button type="submit" class="btn btn-primary mr-2">Submit</button>
                    @if (!empty($previous_url))
                        <a href="{{$previous_url}}" class="btn btn-warning">Back</a>
                    @else
                        <a href="{{route('admin.master-product.index')}}" class="btn btn-warning">Back</a>
                    @endif
                </div>
        </form>
    </div>
</div>
@endsection
