@extends('layout.app', ['activePage' => 'broker', 'titlePage' => __('broker')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Broker
                        </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <form action="{{ route('admin.broker.update',$broker_details) }}" method="POST" class="mt-3" id="add_config" name="add_config">
                            @csrf @method('PUT')
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active required" for="label">Name</label>
                                        <input id="name" name="name" type="text"  class="form-control" placeholder="name" value="{{ old('name' , $broker_details->name) }}">
                                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Frontend Url</label>
                                        <input id="frontend_url" name="frontend_url" type="text"  class="form-control" placeholder="frontend_url" value="{{ old('frontend_url' , $broker_details->frontend_url) }}">
                                        @error('frontend_url')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Backend Url</label>
                                        <input id="backend_url" name="backend_url" type="text"  class="form-control" placeholder="backend_url" value="{{ old('backend_url' , $broker_details->backend_url) }}">
                                        @error('backend_url')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Support Email</label>
                                        <input id="support_email" name="support_email" type="email"  class="form-control" placeholder="support_email" value="{{ old('support_email' , $broker_details->support_email) }}">
                                        @error('support_email')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Support Tollfree</label>
                                        <input id="support_tollfree" name="support_tollfree" type="number"  class="form-control" placeholder="support_tollfree" value="{{ old('support_tollfree' , $broker_details->support_tollfree) }}">
                                        @error('support_tollfree')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Environment</label>
                                        <select name="environment" id="environment" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                            <option {{ old('environment' , $broker_details->environment) == 'uat' ? 'selected' : '' }} value="uat">Uat</option>
                                            <option {{ old('environment' , $broker_details->environment) == 'preprod' ? 'selected' : '' }} value="preprod">Preprod</option>
                                            <option {{ old('environment' , $broker_details->environment) == 'prod' ? 'selected' : '' }} value="prod">Prod</option>
                                        </select>
                                        @error('environment')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>

                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="required">Status</label>
                                        <select name="status" id="status" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true">
                                            <option {{ old('status' , $broker_details->status) == 'active' ? 'selected' : '' }} value="active">Active</option>
                                            <option {{ old('status' , $broker_details->status) == 'inactive' ? 'selected' : '' }} value="inactive">InActive</option>
                                          </select>
                                        @error('status')<span class="text-danger">{{ $message }}</span>@enderror
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
