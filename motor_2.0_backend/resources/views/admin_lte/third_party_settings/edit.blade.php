@extends('admin_lte.layout.app', ['activePage' => 'third_party_settings', 'titlePage' => __('Third Party Settings')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.third_party_settings.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <div class="card-header">
    <h3 class="card-title">Edit Third Party Settings</h3>
    </div>
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.third_party_settings.update',$configs->id) }}" method="POST" class="mt-3" id="add_config" name="add_config">
            @csrf @method('PUT')
            <div class="row mb-3">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active required" for="label">Type</label>
                        <input id="label" name="name" type="text"  class="form-control" placeholder="name" value="{{ old('name' , $configs->name) }}">
                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required">Url</label>
                        <input id="config_key" name="url" type="text"  class="form-control" placeholder="Url" value="{{ old('frontend_url' , $configs->url) }}">
                        @error('url')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-6">
                <div class="form-group">
                        <label class="method required" for="label">Status</label>
                        <select class="form-control select2" name="method" required>
                            {{-- <option selected>Open this select active </option> --}}
                            <option value="POST" {{ ($configs->method == 'POST') ? 'selected' : '' }}>POST</option>
                            <option value="GET" {{ ($configs->method == 'GET') ? 'selected' : '' }}>GET</option>
                        </select>
                        @error('method')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label>option</label>
                        <input id="config_key" name="option" type="text"  class="form-control" placeholder="option" value="{{ old('option' , json_encode($configs->options)) }}">
                        @error('option')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                
                <div class="col-sm-12">
                    <div class="form-group">
                        <label>Headers</label>
                        <input id="config_key" name="api_headers" type="text"  class="form-control" placeholder="Headers" value="{{ old('api_headers' , json_encode($configs->headers)) }}">
                        @error('api_headers')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            
                <div class="col-sm-12">
                <div class="form-group">
                        <label for="label">Body</label>
                        <textarea class="form-control" rows="3" name="body">{{json_encode($configs->body)}}</textarea> 
                        @error('body')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-3">
                    <div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>
@endsection