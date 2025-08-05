@extends('admin_lte.layout.app', ['activePage' => 'configuration', 'titlePage' => __('Configuration')])
@section('content')
<!-- general form elements disabled -->
<a  href="{{ route('admin.ckyc_verification_types.index') }}" class="btn btn-dark mb-4"><i class=" fa fa-solid fa-arrow-left"></i></i></a>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Add Ckyc Verification Type</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.ckyc_verification_types.store') }}" method="POST" class="mt-3" name="add_config" >
            @csrf  @method('POST')
            <div class="row mb-3">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active" for="label">Company Alias</label>
                        <select class="form-control" aria-label="Default select" name="company_alias">
                            <option value=""  selected>Select an option</option>
                            @foreach ($company_alias as $data)
                            <option value="{{$data->company_alias}}">{{$data->company_alias}}</option>
                            @endforeach
                        </select>
                        @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        <label class="active" for="label">Mode</label>
                        <select class="form-control" aria-label="Default select" name="mode">
                        <option value="" selected> select </option>
                        <option value="redirection">Redirection</option>
                        <option value="api">Api</option>
                        </select>
                        @error('active')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </div>
        </form>

    </div>
</div>
@endsection
@section('scripts')

    <script>
        $(document).ready(function() {
            $('.table').DataTable();
            $(document).on('click','.fa.fa-copy', function() {
                var APP_URL = {!! json_encode(url('/')) !!}
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
            });        });

    </script>
@endesction
