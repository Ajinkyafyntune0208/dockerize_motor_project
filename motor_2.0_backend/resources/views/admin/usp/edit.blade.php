@extends('admin.layout.app')

@section('content')
<main class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title"><span class="text-capitalize">{{ request()->usp_type ?? '' }}</span> USP</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 text-right">
                    <a href="{{ url()->previous() }}" class="btn btn-sm btn-primary"><i class="fa fa-arrow-circle-left"></i> Back</a>
                </div>
            </div>

            @if (session('status'))
            <div class="alert alert-{{ session('class') }}">
                {{ session('status') }}
            </div>
            @endif
            <form action="{{ route('admin.usp.update', [ $id, 'usp_type' => request()->usp_type ]) }}" method="post">@csrf @method('PUT')
                <div class="form-group">
                    <label for="">IC Name</label>
                    <select name="ic_alias" class="selectpicker w-100 @error('ic_alias') is-invalid @enderror" data-style="btn-primary" data-live-search="true">
                        <option value="" selected>Select Any One</option>
                        @foreach($master_companies as $company)
                        <option {{ old('ic_alias', $usp->ic_alias) == ($company->company_alias ?? null) ? 'selected' : '' }} value="{{ $company->company_alias ?? '' }}">{{ $company->company_name . ' - ' . $company->company_alias }}</option>
                        @endforeach
                    </select>
                    @error('ic_alias') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="">USP Desc</label>
                    <input type="text" name="usp_desc" class="form-control @error('usp_desc') is-invalid @enderror" placeholder="USP Desc" value="{{ old('usp_desc', $usp->usp_desc) }}">
                    @error('usp_desc') <span class="text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"> Submit</i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
@endsection
@push('scripts')
<script>
    $(document).ready(function() {

    });
</script>
@endpush