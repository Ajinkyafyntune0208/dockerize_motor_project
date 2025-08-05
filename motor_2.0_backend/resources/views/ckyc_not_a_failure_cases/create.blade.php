@extends('layout.app', ['activePage' => 'configuration', 'titlePage' => __('Configuration')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Add CKYC Failure message</h5>

                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.ckyc_not_a_failure_cases.store') }}" method="POST" class="mt-3" name="add_config" >
                            @csrf  @method('POST')
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active required" for="label">Type</label>
                                        <input id="label" name="type" type="text"  class="form-control" placeholder="type" value="{{ old('type') }}" required>
                                        @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Message</label>
                                        <input id="config_key" name="message" type="text"  class="form-control" placeholder="message" value="{{ old('message') }}" required>
                                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active required" for="label">Status</label>
                                        <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" aria-label="Default select example" name="active">
                                            <option selected>Open this select active </option>
                                            <option value="0">Inactive</option>
                                            <option value="1">Active</option>
                                        </select>
                                        @error('active')<span class="text-danger">{{ $message }}</span>@enderror
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
@endpush
