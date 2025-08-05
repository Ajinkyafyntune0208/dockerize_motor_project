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

                        <form action="{{ route('admin.configuration.update',$configs->id) }}" method="POST" class="mt-3" id="add_config" name="add_config">
                            @csrf @method('PUT')
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active required" for="label">Label</label>
                                        <input id="label" name="label" type="text"  class="form-control" placeholder="Label" value="{{ old('label' , $configs->label) }}">
                                        @error('label')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Key</label>
                                        <input id="config_key" name="config_key" type="text"  class="form-control" placeholder="Key" value="{{ old('config_key' , $configs->key) }}">
                                        @error('config_key')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Value</label>
                                        <input id="value" name="value" type="text"  class="form-control" placeholder="value" value="{{ old('value' , $configs->value) }}">
                                        @error('value')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Environment</label>
                                        <select name="environment" id="environment" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100 mx-2" data-live-search="true">
                                            <option {{ old('environment' , $configs->environment) == 'local' ? 'selected' : '' }} value="local">Local</option>
                                            <option {{ old('environment' , $configs->environment) == 'test' ? 'selected' : '' }} value="test">Test</option>
                                            <option {{ old('environment' , $configs->environment) == 'production' ? 'selected' : '' }} value="production">Production</option>
                                        </select>

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
@push('scripts')

    <script>
          $(document).ready(function() {
            $('.table').DataTable();
            $(document).on('click','.fa.fa-copy', function() {
                // $('.fa.fa-copy').click(function() {
                var text = $(this).parent('td').text();
                const elem = document.createElement('textarea');
                elem.value = text;
                document.body.appendChild(elem);
                elem.select();
                document.execCommand('copy');
                document.body.removeChild(elem);
                alert('Text copied...!')
            });
        });

    </script>
@endpush
