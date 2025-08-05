@extends('admin_lte.layout.app', ['activePage' => 'broker', 'titlePage' => __('Broker Details')])
@section('content')
    <!-- general form elements disabled -->
    <a  href="{{ route('admin.broker.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
    <div class="card card-primary">
        <!-- /.card-header -->
        <div class="card-body">
            <form action="{{ route('admin.broker.update', $broker_details->id) }}" method="POST" class="mt-3" id="add_config"
                name="add_config">
                @csrf @method('PUT')
                <div class="row mb-3">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="active required" for="label">Name</label>
                            <input id="label" name="name" type="text" class="form-control" placeholder="name"
                                value="{{ old('name', $broker_details->name) }}">
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Frontend Url</label>
                            <input id="config_key" name="frontend_url" type="text" class="form-control"
                                placeholder="Frontend Url"
                                value="{{ old('frontend_url', $broker_details->frontend_url) }}">
                            @error('frontend_url')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Backend Url</label>
                            <input id="config_key" name="backend_url" type="text" class="form-control"
                                placeholder="Backend Url" value="{{ old('backend_url', $broker_details->backend_url) }}">
                            @error('backend_url')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Support Email</label>
                            <input id="config_key" name="support_email" type="text" class="form-control"
                                placeholder="Support Email"
                                value="{{ old('support_email', $broker_details->support_email) }}">
                            @error('support_email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Support Tollfree</label>
                            <input id="config_key" name="support_tollfree" type="text" class="form-control"
                                placeholder="Support Tollfree"
                                value="{{ old('support_tollfree', $broker_details->support_tollfree) }}">
                            @error('support_tollfree')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Environment</label>
                            <select class="form-control select2" name="environment">
                                <option value="UAT" {{ $broker_details->environment == 'uat' ? 'selected' : '' }}>UAT
                                </option>
                                <option value="Preprod"
                                    {{ $broker_details->environment == 'preprod' ? 'selected' : '' }}>Preprod</option>
                                <option value="Prod" {{ $broker_details->environment == 'prod' ? 'selected' : '' }}>
                                    Prod</option>
                            </select>
                            @error('environment')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="active required" for="label">Status</label>
                            <select class="form-control select2" name="status">

                                {{-- <option selected>Open this select active </option> --}}
                                <option value="inactive" {{ $broker_details->status == 'inactive' ? 'selected' : '' }}>
                                    Inactive</option>
                                <option value="active" {{ $broker_details->status == 'active' ? 'selected' : '' }}>Active
                                </option>
                            </select>
                            @error('status')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-3 text-right">
                        <div class="d-flex justify-content-center">
                            <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Submit</button>

                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection('content')
