@extends('admin_lte.layout.app', ['activePage' => 'create-forumla', 'titlePage' => __('Premium Calculation Config')])
@section('content')
<div class="content-wrapper">
    <div class="col-lg-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                @if (session('status'))
                <div class="alert alert-{{ session('class') }}">
                    {{ session('status') }}
                </div>
                @endif
                <form action="" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                            <label for="">Insurance Company</label>
                            <select name="insuranceCompany" id="pos" class="selectpicker w-100" data-live-search="true"
                                data-style="btn-sm btn-primary" data-actions-box="true" required>
                                <option value="" selected disabled>Please slect</option>
                                @foreach ($icList as $ic)
                                <option value="{{ $ic->company_id }}">{{ $ic->company_alias }}</option>
                                @endforeach
                            </select>
                            @error('insuranceCompany')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            @foreach ($expressionList as $exp)
                                <div class="row my-4">
                                    <div class="col-4 h5">
                                        {{ $loop->iteration }}. {{$exp->expression_name}} :
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection