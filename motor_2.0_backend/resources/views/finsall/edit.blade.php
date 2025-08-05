@extends('layout.app', ['activePage' => 'configuration', 'titlePage' => __('Configuration')])
@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Finsall Configuration
                    </h5>


                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif

                    <form action="{{ route('admin.finsall-configuration.update',http_build_query($request)) }}" method="POST" class="mt-3" id="add_config" name="add_config">
                            @csrf @method('PUT')
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Section</label>
                                    <select name="company" id="company" class="form-control">
                                        @foreach ($companies as $company)
                                        <option value="{{$company->company_alias}}" {{
                                            ($company->company_alias == $request['company']) ? 'selected' : ''
                                        }}>{{$company->company_name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Section</label>
                                    <select name="section" id="section" class="form-control">
                                        @foreach ($sections as $section)
                                        <option value="{{$section->product_sub_type_id}}" {{
                                            ($section->product_sub_type_id == $request['section']) ? 'selected' : ''
                                        }}>{{$section->product_sub_type_code}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Premium Type</label>
                                    <select name="premium_type" id="premium_type" class="form-control">
                                        @foreach ($premium_types as $premium_type)
                                        <option value="{{$premium_type->premium_type_code}}" {{
                                            ($premium_type->premium_type_code == $request['premium_type']) ? 'selected' : ''
                                        }}>{{$premium_type->premium_type}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Active</label>
                                    <select name="active" id="active" class="form-control">
                                        <option value="N" {{
                                            ("N" == $request['active']) ? 'selected' : ''
                                        }}>N</option>
                                        <option value="Y" {{
                                            ("Y" == $request['active']) ? 'selected' : ''
                                        }}>Y</option>
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
        $(document).on('click', '.fa.fa-copy', function() {
            var APP_URL = {
                !!json_encode(url('/')) !!
            }
            console.log(APP_URL);
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