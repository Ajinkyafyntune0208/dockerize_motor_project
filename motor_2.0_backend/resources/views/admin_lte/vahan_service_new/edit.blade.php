@extends('admin_lte.layout.app', ['activePage' => 'vahan', 'titlePage' => __('vahan')])
@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
        Add-service
        </h3>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.vahan_service.update', $id) }}" method="POST" class="mt-3"
            name="edit_service">
            @csrf @method('PUT')
            <div class="row mb-3">
                <div class="col-sm-4">
                    <div class="form-group">
                        <label class="required">Vahan Service Name</label>
                        <input id="service_name" name="service_name" type="text" class="form-control"
                            placeholder="service Name" value="{{ old('service_name') ? old('service_name') : (empty($vahan_service->vahan_service_name) ? '' : $vahan_service->vahan_service_name)}}">
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
                            value="{{ old('service_code') ? old('service_code') : (empty($vahan_service->vahan_service_name_code)? '' : $vahan_service->vahan_service_name_code) }}">
                        @error('service_code')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label class="required">Status</label>
                        <select name="status" id="status"
                            class="select2 form-control w-100" required>
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
                        <button type="submit" class="btn btn-primary"
                            style="margin-top: 30px;">Submit</button>
                    </div>
                </div>
        </form>
    </div>
</div>
@endsection
