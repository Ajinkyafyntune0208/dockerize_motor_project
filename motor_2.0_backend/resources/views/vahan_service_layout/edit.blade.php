@extends('vahan_service_layout.app')
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Add-service</h5>

                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.vahan_service.update', $id) }}" method="POST" class="mt-3"
                            name="edit_service">
                            @csrf @method('PUT')
                            <div class="row mb-3">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Vahan Service Name</label>
                                        <input id="service_name" name="service_name" type="text" class="form-control"
                                            placeholder="service_name Name" value="{{ old('service_name') ? old('service_name') : (empty($vahan_service->vahan_service_name) ? '' : $vahan_service->vahan_service_name)}}">
                                        @error('service_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Vahan Service Name code</label>
                                        <input id="service_code" name="service_code" type="text"
                                            class="form-control" placeholder="code"
                                            value="{{ old('service_code') ? old('service_code') : (empty($vahan_service->vahan_service_name_code)? '' : $vahan_service->vahan_service_name_code) }}">
                                        @error('service_code')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" id="status" data-style="btn-sm btn-primary"
                                            class="selectpicker w-100">
                                            <option value="">Nothing selected</option>
                                            @if(old('status'))
                                            <option @if (old('status') == "Active") {{ 'selected' }} @endif
                                            value="Active">Active</option>
                                            <option @if (old('status') == "Inactive") {{ 'selected' }} @endif
                                            value="Inactive">Inactive</option>
                                            @else
                                            <option {{ $vahan_service->status == 'Active' ? 'selected' : '' }}
                                                value="Active">Active</option>
                                            <option {{ $vahan_service->status == 'Inactive' ? 'selected' : '' }}
                                                value="Inactive">
                                                Inactive</option>
                                                @endif
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
