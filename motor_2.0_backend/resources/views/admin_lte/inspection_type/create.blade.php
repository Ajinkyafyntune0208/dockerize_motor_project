@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Inspection Type')])
@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.inspection.store') }}" method="POST" class="mt-3">
            @csrf @method('POST')
            <div class="row mb-3">
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Company Name </label>
                        <select id="company_name" name="company_id" class="form-control">
                            <option value="">Select</option>
                            @foreach($data as $menu)
                            <option value="{{$menu->company_id}}">{{$menu->company_alias}}</option>
                            @endforeach

                        </select>
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label>Manual <span style="color: red;">*</span> </label> <br>
                        <select class="form-control" id="manual_type" name="manual_type">
                            <option value="">Select</option>
                            <option value="Y" >Yes</option>
                            <option value="N">No</option>

                        </select>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label>Self <span style="color: red;">*</span> </label> <br>
                        <select class="form-control" id="self_type" name="self_type">
                            <option value="">Select</option>
                            <option value="Y" {{ $menu->Self_inspection == 'Y' ? 'selected':'' }}>Yes</option>
                            <option value="N" {{ $menu->Self_inspection == 'N' ? 'selected':'' }}>No</option>

                        </select>
                    </div>
                </div>

                <div class="col-12 d-flex mt-3">
                    <button type="submit" class="btn btn-primary mr-2">Submit</button>

                </div>
            </div>
        </form>
    </div>
</div>
@endsection