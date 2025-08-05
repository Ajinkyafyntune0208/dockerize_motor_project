@extends('layout.app', ['activePage' => 'configuration', 'titlePage' => __('Configuration')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ckyc Not A Failure Cases
                        </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.ckyc_not_a_failure_cases.update',$CKYCNotAFailureCasess->id) }}" method="POST" class="mt-3" id="add_config" name="add_config">
                            @csrf @method('PUT')
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active required" for="label">Type</label>
                                        <input id="label" name="type" type="text"  class="form-control" placeholder="type" value="{{ old('type' , $CKYCNotAFailureCasess->type) }}">
                                        @error('type')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Message</label>
                                        <input id="config_key" name="message" type="text"  class="form-control" placeholder="message" value="{{ old('message' , $CKYCNotAFailureCasess->message) }}">
                                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active required" for="label">Status</label>
                                        <select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" aria-label="Default select example" name="active">
                                            {{-- <option selected>Open this select active </option> --}}
                                            <option value="0" {{ ($CKYCNotAFailureCasess->active == 0) ? 'selected' : '' }}>Inactive</option>
                                            <option value="1" {{ ($CKYCNotAFailureCasess->active == 1) ? 'selected' : '' }}>Active</option>
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
