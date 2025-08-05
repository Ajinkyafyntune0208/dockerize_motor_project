@extends('layout.app', ['activePage' => 'broker', 'titlePage' => __('broker')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">broker
                        </h5>

                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.broker.store') }}" method="POST" class="mt-3" name="add_broker_details" >
                            @csrf  @method('POST')
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active" for="label">Name</label>
                                        <input id="name" name="name" type="text"  class="form-control" placeholder="name" value="{{ old('name') }}">
                                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Frontend url</label>
                                        <input id="frontend_url" name="frontend_url" type="text"  class="form-control" placeholder="frontend_url" value="{{ old('frontend_url') }}">
                                        @error('frontend_url')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                         <label>Backend url</label>
                                        <input id="backend_url" name="backend_url" type="text"  class="form-control" placeholder="backend_url" value="{{ old('backend_url') }}">
                                        @error('backend_url')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Support Email</label>
                                        <input id="support_email" name="support_email" type="email"  class="form-control" placeholder="support_email" value="{{ old('support_email') }}">
                                        @error('support_email')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Support Tollfree</label>
                                        <input id="support_tollfree" name="support_tollfree" type="number"  class="form-control" placeholder="support_tollfree" value="{{ old('support_tollfree') }}">
                                        @error('support_tollfree')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Environment</label>
                                        <select name="environment" id="environment" class="form-control">
                                            <option {{ old('environment') == 'uat' ? 'selected' : '' }} value="Uat">Uat</option>
                                            <option {{ old('environment') == 'preprod' ? 'selected' : '' }} value="Preprod">Preprod</option>
                                            <option {{ old('environment') == 'prod' ? 'selected' : '' }} value="Prod">Prod</option>
                                        </select>

                                    </div>

                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" id="status" class="form-control">
                                            <option {{ old('status') == 'active' ? 'selected' : '' }} value="active">Active</option>
                                            <option {{ old('status') == 'inactive' ? 'selected' : '' }} value="inactive">InActive</option>
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
