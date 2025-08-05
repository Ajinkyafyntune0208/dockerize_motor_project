@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Inspection Type')])
@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ url('admin/inspection/update') }}" method="POST" class="mt-3">
            @csrf @method('POST')
            <div class="row mb-3">
                @foreach($data as $menu)
                <input type="hidden" name="id" value="{{$menu->id}}" />
                @endforeach

                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Company Name </label>
                        <select id="company_name" name="company_name" class="form-control">
                            <!-- <option value="0">None</option> -->
                            @foreach($data as $menu)
                            <option value="{{$menu->company_name}}">{{$menu->company_name}}</option>
                            @endforeach

                        </select>
                    </div>
                </div>

                <div class="col-sm-3">
                    <div class="form-group">
                        <label>Manual <span style="color: red;">*</span> </label> <br>
                        <select class="form-control" id="manual_type" name="manual_type">
                            @foreach($data as $menu)
                            <option value="">Select</option>
                            <option value="Y" {{ $menu->Manual_inspection == 'Y' ? 'selected':'' }}>Yes</option>
                            <option value="N" {{ $menu->Manual_inspection == 'N' ? 'selected':'' }}>No</option>
                            @endforeach

                        </select>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label>Self <span style="color: red;">*</span> </label> <br>
                        <select class="form-control" id="self_type" name="self_type">
                            @foreach($data as $menu)
                            <option value="">Select</option>
                            <option value="Y" {{ $menu->Self_inspection == 'Y' ? 'selected':'' }}>Yes</option>
                            <option value="N" {{ $menu->Self_inspection == 'N' ? 'selected':'' }}>No</option>
                            @endforeach

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