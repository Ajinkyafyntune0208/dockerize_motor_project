@extends('admin_lte.layout.app', ['activePage' => 'configuration', 'titlePage' => __('Configuration')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.configuration.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <div class="card-header">
    <h3 class="card-title">Configuration</h3>
    </div>
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.configuration.update',$configs->id) }}" method="POST" class="mt-3" id="add_config" name="add_config">
            @csrf @method('PUT')
            <div class="row mb-3">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active required" for="label">Label</label>
                        <input id="label" name="label" type="text"  class="form-control" required placeholder="Label" value="{{ old('label' , $configs->label) }}">
                        @error('label')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required" for="config_key">Key</label>
                        <input id="config_key" name="config_key" type="text"  class="form-control" required placeholder="Key" value="{{ old('config_key' , $configs->key) }}">
                        @error('config_key')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="required" for="value">Value</label>
                        <input id="value" name="value" type="text"  class="form-control" required placeholder="value" value="{{ old('value' , $configs->value) }}">
                        @error('value')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="form-group">
                        <label for="valueType"> Type of Value</label>
                        <select name="valueType" id="valueType" class="form-control">
                            <option value="" {{!empty(old('value' , $configs->value)) ? 'selected' : ''}}>Custom Value</option>
                            <option value="empty" {{empty(old('value' , $configs->value)) && !is_null(old('value' , $configs->value)) ? 'selected' : ''}}>Empty String</option>
                            <option value="nullable" {{empty(old('value' , $configs->value)) && is_null(old('value' , $configs->value)) ? 'selected' : ''}}>Nullable</option>
                        </select>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <div class="form-group">
                        <label class="required">Environment</label>
                        <select name="environment" id="environment" class="select2 w-100 form-control" data-live-search="true">
                            <option {{ old('environment' , $configs->environment) == 'local' ? 'selected' : '' }} value="local">Local</option>
                            <option {{ old('environment' , $configs->environment) == 'test' ? 'selected' : '' }} value="test">Test</option>
                            <option {{ old('environment' , $configs->environment) == 'production' ? 'selected' : '' }} value="production">Production</option>
                        </select>

                    </div>

                </div>
                <div class="col-12"></div>
                <div class="col-sm-3 text-right">
                    <div class="d-flex">
                        <button type="submit" class="btn btn-primary">Submit</button>

                    </div>

                </div>
            </div>
        </form>
    </div>
</div>

<script>
    onload = ()=> {
        validatevalueType(document.querySelector('#valueType').value)
    }
    document.querySelector('#valueType').addEventListener('change', (e) => {
        validatevalueType(e.target.value)
    })

    function validatevalueType(type) {
        if (type == 'nullable' || type == 'empty') {
            document.querySelector('[name="value"]').setAttribute('disabled', 'disabled')
            document.querySelector('[name="value"]').value = '';
        } else {
            document.querySelector('[name="value"]').removeAttribute('disabled')
        }
    }
</script>
@endsection('content')