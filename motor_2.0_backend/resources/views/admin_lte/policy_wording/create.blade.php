@extends('admin_lte.layout.app', ['activePage' => 'policy-wording', 'titlePage' => __('Policy Wording')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.policy-wording.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.policy-wording.store', rand()) }}" enctype="multipart/form-data"
                        method="post">@csrf
            <div class="row">
                <!-- text input -->
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Company Name <span class="text-danger"> *</span></label>
                        <select name="company_name" id="company_name" class="form-control select2" required data-live-search="true">
                            <option value="">Nothing selected</option>
                            @foreach ($master_policies->unique('company_alias') as $key => $policy)
                                <option {{ old('policy') == $policy->company_alias ? 'selected' : '' }}
                                    value="{{ $policy->company_alias }}">{{ $policy->company_alias }}
                                </option>
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Section <span class="text-danger"> *</span></label>
                        <select name="section" id="section" class="form-control select2" required data-live-search="true">
                           <option value="">Nothing selected</option>
                            @foreach ($master_policies->unique('product_sub_type_code') as $key => $policy)
                                <option
                                    {{ old('policy') == $policy->product_sub_type_code ? 'selected' : '' }}
                                    value="{{ $policy->product_sub_type_code }}">
                                    {{ $policy->product_sub_type_code }}</option>
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Business Type <span class="text-danger"> *</span></label>
                        <select name="business_type" id="business_type" required
                        class="form-control select2" data-live-search="true">
                            <option value="">Nothing selected</option>
                            <option value="newbusiness">newbusiness</option>
                            <option value="rollover">rollover</option>
                            <option value="third_party">third_party</option>
                            <option value="own_damage">own_damage</option>
                        </select>
                        @error('message') <span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Policy Type <span class="text-danger"> *</span></label>
                        <select name="policy_type" id="policy_type"required
                        class="form-control select2" data-live-search="true">
                            <option value="">Nothing selected</option>
                            @foreach($master_premium_types as $key => $master_premium_type)
                            <option value="{{ $master_premium_type->premium_type }}">{{ $master_premium_type->premium_type }}</option>
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Select Policy Wording File <span class="text-danger"> *</span> :</label>
                        <label class="btn btn-primary mb-0"></i>
                        <input type="file" name="file" accept="application/pdf" required></label>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-12 text-right">
                    <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Submit</button>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection('content')