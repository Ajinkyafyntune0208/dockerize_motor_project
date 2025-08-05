@extends('admin_lte.layout.app', ['activePage' => 'third_party_settings', 'titlePage' => __('Third Party Settings')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.third_party_settings.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <div class="card-body">
        <form action="{{ route('admin.third_party_settings.store') }}" method="POST">@csrf
            <div class="row">
                <!-- text input -->
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required">Name</label>
                        <input type="text" class="form-control" placeholder="name" name="name"  required>
                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required">Url</label>
                        <input type="text" class="form-control" placeholder="url" name="url" required>
                        @error('url')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-6">
                <div class="form-group">
                        <label class="active required" for="label">Method</label>
                        <select class="form-control select2" name="method">
                           <option value="POST">POST</option>
                            <option value="GET">GET</option>
                        </select>
                        @error('method')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div> 

                <div class="col-sm-6">
                    <div class="form-group">
                        <label>Options</label>
                        <input type="text" class="form-control" name="option" placeholder="option">
                        @error('option')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="form-group">
                        <label>Headers</label>
                        <input type="text" class="form-control" name="api_headers" placeholder="Headers">
                        @error('api_headers') <span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-12">
                <div class="form-group">
                        <label class="body" for="label">Body</label>
                        <textarea class="form-control" rows="3" name="body"></textarea> 
                        @error('body')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div> 
                <div class="col-sm-3">
                    <div>
                        <button type="submit" class="btn btn-primary" >Submit</button>
                    </div>

            </div>
        </form>
    </div>
</div>
@endsection