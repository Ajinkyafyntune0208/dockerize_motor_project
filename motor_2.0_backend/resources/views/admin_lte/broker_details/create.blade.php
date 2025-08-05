@extends('admin_lte.layout.app', ['activePage' => 'broker_details', 'titlePage' => __('Broker Details')])
@section('content')
    <!-- general form elements disabled -->
    <a  href="{{ route('admin.broker.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
    <div class="card card-primary">

        <!-- /.card-header -->
        <div class="card-body">
            <form action="{{ route('admin.broker.store') }}" method="POST">@csrf
                <div class="row">
                    <!-- text input -->
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Name</label>
                            <input type="text" class="form-control" placeholder="Name" name="name"
                                value="{{ old('name') }}" required>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Frontend url</label>
                            <input type="text" class="form-control" placeholder="Frontend url" name="frontend_url"
                                value="{{ old('frontend_url') }}" required>
                            @error('frontend_url')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Backend url</label>
                            <input type="text" class="form-control" name="backend_url" placeholder="Backend ur"
                                value="{{ old('backend_url') }}">
                            @error('backend_url')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Support Email</label>
                            <input type="email" class="form-control" name="support_email" placeholder="Support Email"
                                value="{{ old('support_email') }}">
                            @error('support_email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Support Tollfree</label>
                            <input type="text" class="form-control" name="support_tollfree"
                                placeholder="Support Tollfree" value="{{ old('support_tollfree') }}">
                            @error('support_tollfree')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="required">Environment</label>
                            <select class="form-control select2" name="environment">
                                <option value="UAT">UAT</option>
                                <option value="Preprod">Preprod</option>
                                <option value="Prod">Prod</option>
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
                                <option value="inactive">Inactive</option>
                                <option value="active">Active</option>
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
            </form>
        </div>
    </div>
@endsection('content')
