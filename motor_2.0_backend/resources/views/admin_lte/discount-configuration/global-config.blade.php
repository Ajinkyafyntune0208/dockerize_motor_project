@extends('admin_lte.layout.app', ['activePage' => 'discount-configurations', 'titlePage' => __('Discount Configuration')])
@section('content')
<div class="row">
    <div class="col-md-6 col-lg-4">
        @if (session('error'))
        <div class="alert alert-danger mt-3 py-1">
            {{ session('error') }}
        </div>
        @endif
    </div>
</div>
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                @include('admin_lte.discount-configuration.tabs')
                <div class="tab-content" id="">
                    <div class="tab-pane fade show active"  id="nav-global-config" role="tabpanel" aria-labelledby="nav-global-config-tab">
                        <form name="global-config-form" method="post">
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-3 col-lg-3 col-sm-6">
                                    <label for="">Global Percentage for All <span class="text-danger"> *</span> : </label>
                                </div>
                                <div class="form-group col-md-6 col-lg-4 col-sm-6">
                                    <input type="number" name="globalConfig" id="" class="form-control" value="{{old('globalConfig') ?? $globalConfig}}" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button class="btn btn-primary">Save</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
