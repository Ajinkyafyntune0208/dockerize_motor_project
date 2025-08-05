@extends('admin_lte.layout.app', ['activePage' => 'ckyc_not_a_failure_cases', 'titlePage' => __('CKYC Not A Failure Cases')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.ckyc_not_a_failure_cases.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <div class="card-header">
    <h3 class="card-title">Add CKYC Failure message</h3>
    </div>
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.ckyc_not_a_failure_cases.store') }}" method="POST" class="mt-3" name="add_config" >
            @csrf  @method('POST')
            <div class="row mb-3">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active required" for="label">Type</label>
                        <input id="label" name="type" type="text"  class="form-control" placeholder="type" value="{{ old('type') }}" required>
                        @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required">Message</label>
                        <input id="config_key" name="message" type="text"  class="form-control" placeholder="message" value="{{ old('message') }}" required>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active required" for="label">Status</label>
                        <select class="form-control select2" style="width: 100%;" name="active" required>
                            <option value="0">Inactive</option>
                            <option value="1">Active</option>
                        </select>
                        @error('active')<span class="text-danger">{{ $message }}</span>@enderror
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