@extends('layout.app', ['activePage' => 'vahan', 'titlePage' => __('Vahan')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Add service</h5>

                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.vahan_service.store') }}" method="POST" class="mt-3"
                            name="add_service">
                            @csrf @method('POST')
                            <div class="row mb-3">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label class="required">Vahan Service Name</label>
                                        <input id="service_name" name="service_name" type="text" class="form-control"
                                            placeholder="Service Name" value="{{ old('service_name') }}" required>
                                        @error('service_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label class="required">Vahan Service Name code</label>
                                        <input id="service_code" name="service_code" type="text"
                                            class="form-control" placeholder="code"
                                            value="{{ old('service_code') }}" required>
                                        @error('service_code')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label class="required">Status</label>
                                        <select name="status" id="status" data-style="btn-sm btn-primary"
                                            class="selectpicker w-100" required>
                                            <option value="">Nothing selected</option>
                                            <option {{ old('status') == 'Active' ? 'selected' : '' }} value="Active">
                                                Active</option>
                                            <option {{ old('status') == 'Inactive' ? 'selected' : '' }} value="Inactive">
                                                Inactive</option>
                                        </select>
                                        @error('status')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-outline-primary btn-sm"
                                            style="margin-top: 30px;">Submit</button>
                                    </div>
                                </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
