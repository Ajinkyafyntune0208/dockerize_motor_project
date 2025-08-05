@extends('layout.app')
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        @if($read=='')
                        <h5 class="card-title">Edit error list</h5>
                        @else
                        <h5 class="card-title">View error list</h5>
                        @endif

                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.error-list-master.update', $id) }}" method="POST" class="mt-3"
                            name="edit_error_list">
                            @csrf @method('PUT')
                            <div class="row mb-3">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Error Name</label>
                                        <input id="error_name" name="error_name" type="text" class="form-control"
                                            placeholder="Error Name"
                                            value="{{ old('error_name') ? old('error_name') : (empty($errorList->error_name) ? ' ' : $errorList->error_name) }}"
                                            required {{ $read }}>
                                        @error('error_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Error Code</label>
                                        <input id="error_code" name="error_code" type="text" class="form-control"
                                            placeholder="code"
                                            value="{{ old('error_code') ? old('error_code') : (empty($errorList->error_code) ? ' ' : $errorList->error_code) }}"
                                            required {{ $read }}>
                                        @error('error_code')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>IC Name</label>
                                        <input id="ic_name" name="ic_name" type="text" class="form-control"
                                            placeholder="IC Name"
                                            value="{{ old('ic_name') ? old('ic_name') : (empty($errorList->ic_name) ? ' ' : $errorList->ic_name) }}"
                                            required {{ $read }}>
                                        @error('ic_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Status</label>
                                        @if ($read == 'readonly')
                                            <select name="status" id="status" data-style="btn-sm btn-primary"
                                                class="selectpicker w-100" disabled='true'>
                                            @else
                                            <select name="status" id="status" data-style="btn-sm btn-primary"
                                                class="selectpicker w-100">
                                        @endif
                                        {{-- <option value="">Nothing selected</option> --}}
                                        @if (old('status'))
                                            <option @if (old('status') == 'Active') {{ 'selected' }} @endif
                                                value="Active">Active</option>
                                            <option @if (old('status') == 'Inactive') {{ 'selected' }} @endif
                                                value="Inactive">Inactive</option>
                                        @else
                                            <option {{ $errorList->status == 'Y' ? 'selected' : '' }} value="Active">
                                                Active</option>
                                            <option {{ $errorList->status == 'N' ? 'selected' : '' }} value="Inactive">
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
