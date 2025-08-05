@extends('layout.app', ['activePage' => 'configuration', 'titlePage' => __('Configuration')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ckyc Verification Types
                        </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.ckyc_verification_types.update',$ckyc_verification_edit->id) }}" method="POST" class="mt-3" name="add_config" >
                            @csrf @method('PUT')
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active" for="label">Company Alias</label>
                                        <select class="form-select" aria-label="Default select" name="company_alias">
                                            <option value="{{$ckyc_verification_edit->company_alias}}">{{$ckyc_verification_edit->company_alias}}</option>
                                            @foreach ($company_alias as $data )
                                            <option value="{{$data->company_alias}}">{{$data->company_alias}}</option>  
                                            @endforeach
                                        </select>
                                        @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active" for="label">Mode</label>
                                        <select class="form-select" aria-label="Default select" name="mode">
                                        <option {{ $ckyc_verification_edit->mode == 'api' ? 'selected' : '' }} value="api">Api</option>
                                        <option {{ $ckyc_verification_edit->mode == 'redirection' ? 'selected' : '' }} value="redirection">Redirection</option>
                                        @error('active')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-2 text-right">
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
