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
        <form action="{{ route('admin.ckyc_not_a_failure_cases.update',$CKYCNotAFailureCasess->id) }}" method="POST" class="mt-3" id="add_config" name="add_config">
            @csrf @method('PUT')
            <div class="row mb-3">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active required" for="label">Type</label>
                        <input id="label" name="type" type="text"  class="form-control" placeholder="type" value="{{ old('type' , $CKYCNotAFailureCasess->type) }}">
                        @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required">Message</label>
                        <input id="config_key" name="message" type="text"  class="form-control" placeholder="message" value="{{ old('message' , $CKYCNotAFailureCasess->message) }}">
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active required" for="label">Status</label>
                        <select class="form-control select2" name="active">
                            {{-- <option selected>Open this select active </option> --}}
                            <option value="0" {{ ($CKYCNotAFailureCasess->active == 0) ? 'selected' : '' }}>Inactive</option>
                            <option value="1" {{ ($CKYCNotAFailureCasess->active == 1) ? 'selected' : '' }}>Active</option>
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