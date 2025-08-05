@extends('layout.app', ['activePage' => 'discount-domain', 'titlePage' => __('Disount Domain')])
@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Disount Domain
                            <a href="{{ route('admin.discount-domain.create') }}" class="btn btn-primary btn-sm float-end"><i class="fa fa-arrow-circle-left me-1"></i> Back</a>
                            <a href="{{ route('admin.discount-domain.sample-file') }}" class="btn btn-primary btn-sm float-end me-1"><i class="fa fa-arrow-circle-down me-1"></i> Sample Excel</a>
                        </h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <form action="{{ route('admin.discount-domain.store') }}" method="POST" class="mt-3" enctype="multipart/form-data">
                            @csrf
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active" for="domain">Label</label>
                                        <input id="domain" name="domain" type="text" class="form-control"
                                            placeholder="Domain" value="{{ old('domain') }}">
                                        @error('domain')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="active" for="domain">Select File</label><br>
                                        <input id="file" name="file" type="file" class="btn btn-primary"
                                            placeholder="File" accept=".xlsx" value="">
                                        @error('file')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-sm-3 text-right">
                                    <div class="d-flex justify-content-center">
                                        <button type="submit" class="btn btn-outline-primary"
                                            style="margin-top: 30px;">Submit</button>
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
    <script></script>
@endpush
