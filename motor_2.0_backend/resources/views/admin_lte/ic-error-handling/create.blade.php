@extends('admin_lte.layout.app', ['activePage' => 'ic-error-handling', 'titlePage' => __('IC Error Handler')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.ic-error-handling.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.ic-error-handling.store') }}" method="POST">@csrf
            <div class="row">
                <!-- text input -->
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Company Name <span class="text-danger">*</span></label>
                        <select name="company_alias" id="company_alias"
                            class="form-control select2" data-live-search="true">
                            <option value="" selected>Select Any One</option>
                            @foreach($companies as $company)
                            <option {{ old('ic_alias') == ($company->company_alias ?? null) ? 'selected' : '' }} value="{{ $company->company_alias ?? '' }}">{{ $company->company_alias}}</option>
                            @endforeach
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Section<span class="text-danger">*</span></label>
                        <select name="section" id="section" class="form-control select2" data-live-search="true">
                            <option value="" selected>Select Any One</option>
                            <option value="car">CAR</option>
                            <option value="bike">BIKE</option>
                            <option value="cv">CV</option>
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Type<span class="text-danger">*</span></label>
                        <select name="type" id="type" data-style="btn-primary"
                            class="form-control select2" data-live-search="true">
                            <option value="" selected>Select Any One</option>
                            <option value="quote">Quote</option>
                            <option value="proposal">Proposal</option>
                        </select>
                        @error('message') <span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>IC Error<span class="text-danger">*</span></label>
                        <textarea style="height: 10rem;" class = "form-control" name="ic_error" rows = "3" placeholder="Please Enter IC Error..." ></textarea>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Custom Error<span class="text-danger">*</span></label>
                        <textarea style="height: 10rem;" class = "form-control" rows = "3" placeholder="Please Enter Custom Error..." name="custom_error" ></textarea>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
               <div class="col-sm-6">
                <div class="form-group">
                        <label class="active required" for="label">Status<span class="text-danger">*</span></label>
                        <select class="form-control select2" name="status">
                           <option value="N">Inactive</option>
                            <option value="Y">Active</option>
                        </select>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div> 
                <div class="col-sm-3 text-right">
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Submit</button>
                    </div>
            </div>
        </form>
    </div>
</div>
@endsection('content')