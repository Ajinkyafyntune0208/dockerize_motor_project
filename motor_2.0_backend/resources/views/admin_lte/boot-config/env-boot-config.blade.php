@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Config Boot')])
@section('content')
<div class="container">
    <form action="{{ route('admin.env.update') }}" method="POST">
        @csrf

        <div class="row">
            @foreach($envVars as $key => $value)
                <div class="col-md-3 mb-3"> 
                    <div class="form-group">
                        <label class="fw-bold">{{ $key }}</label>
                        <input type="text" name="{{ $key }}" value="{{ $value }}" class="form-control">
                    </div>
                </div>
            @endforeach
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
@endsection
