@extends('layout.app', ['activePage' => 'configuration', 'titlePage' => __('Configuration')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Configuration
                        </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.third_party_settings.update',$configs->id) }}" method="POST" class="mt-3" id="add_config" name="add_config">
                            @csrf @method('PUT')
                            <input type="text", name="id",  value="{{ old('name' , $configs->id) }}" hidden>
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active required" for="name">Name</label>
                                        <input id="name" name="name" type="text"  class="form-control" placeholder="name" value="{{ old('name' , $configs->name) }}" required>
                                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Url</label>
                                        <input id="url" name="url" type="text"  class="form-control" placeholder="url" value="{{ old('url' , $configs->url) }}" required>
                                        @error('url')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Headers</label>
                                        <input id="api_headers" name="api_headers" type="text"  class="form-control" placeholder="headers" value="{{ old('api_headers' , (is_array($configs->headers) ? json_encode($configs->headers, JSON_UNESCAPED_SLASHES) : $configs->headers)) }}">
                                        @error('api_headers')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Option</label>
                                        <input id="option" name="option" type="text" class="form-control" placeholder="option" value="{{ old('option', (is_array($configs->option) ? json_encode($configs->option, JSON_UNESCAPED_SLASHES) : $configs->option)) }}">
                                        @error('option')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Method</label>
                                        <select name="method" id="method" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                            <option {{ strtoupper(old('method' , $configs->method)) == 'POST' ? 'selected' : '' }} value="POST">POST</option>
                                            <option {{ strtoupper(old('method' , $configs->method)) == 'GET' ? 'selected' : '' }} value="GET">GET</option>
                                        </select>

                                    </div>

                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label>Body</label>
                                        <textarea id="body" name="body" rows="4" cols="115" >{{ old('body' , (is_array($configs->body) ? json_encode($configs->body, JSON_UNESCAPED_SLASHES) : $configs->body)) }}</textarea>
                                        @error('body')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-3 text-right">
                                    <div class="d-flex justify-content-center">
                                        <button type="submit" class="btn btn-outline-primary" style="margin-top: 30px;">Submit</button>

                                    </div>

                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection