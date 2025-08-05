@extends('layout.app', ['activePage' => 'discount-configurations', 'titlePage' => __('Discount Configuration')])
@section('content')
<style>
    .nav-link.active {
        background: #1F3BB3 !important;
        color: #fff !important;
    }
</style>
<div class="content-wrapper">
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Discount Configuration</h4>
                    <div class="row">
                        <div class="col-md-6 col-lg-4">
                            @if (session('error'))
                            <div class="alert alert-danger mt-3 py-1">
                                {{ session('error') }}
                            </div>
                            @endif

                            @if (session('success'))
                            <div class="alert alert-success mt-3 py-1">
                                {{ session('success') }}
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            @include('discount-configuration.tabs')
                            <div class="tab-content" id="">
                                <div class="tab-pane fade show active"  id="nav-global-config" role="tabpanel" aria-labelledby="nav-global-config-tab">
                                    <form name="global-config-form" method="post">
                                        @csrf
                                        <div class="row">
                                            <div class="form-group col-md-4 col-lg-3 col-xl-2">
                                                <label for="">Global Percentage for All : </label>
                                            </div>
                                            <div class="form-group col-md-6 col-lg-4 col-xl-3">
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
        </div>
    </div>
</div>
@endsection
