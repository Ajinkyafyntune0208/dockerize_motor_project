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
                    <div class="tab-pane fade active show" id="nav-setting" role="tabpanel" aria-labelledby="nav-setting-tab">
                        <form action="" method="post">
                            @csrf
                            <div class="row">
                                <div class="form-group col-md-6 col-lg-6 col-xl-4">
                                    <label for="">Select the configuration which you want to be used in the journey <span class="text-danger"> * </span>: </label>
                                </div>
                                <div class="form-group col-md-6 col-lg-4 col-xl-3">
                                    <select name="configType" data-actions-box="true" class="select2 form-control w-100" data-live-search="true" name="configType">
                                        <option value="" selected disabled>Please select</option>
                                        @foreach ($configMethods as $c)
                                        <option value="{{$c->id}}" {{old('configType') ? (old('configType') == $c->id ? 'selected' : '') : ($c->id == $selectedConfigId ? 'selected' : '')}}>{{$c->discount_name}}</option>
                                        @endforeach
                                    </select>
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
