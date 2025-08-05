{{-- @extends('admin_lte.layout.app', ['activePage' => 'user', 'titlePage' => __('Master Configurator')])
@section('content')
<style>
    :root{
    --primary: #1f3bb3;
    }
    .btn-cus{
        width: 100%;
        height: 40px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);

        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-cus:hover{
        background-color: var(--primary);
        color: white;
    }
    .btn-cus.active{
        background-color: var(--primary);
        color: white;
    }
    .rad-cus{
        display: flex;
        gap: 5px;
        margin-top: 5px;

    }
    .chkbox-cus{
        display: flex;
        gap: 5px;
    }
</style>

@if (Session::has('success'))
    <div class="alert alert-success" id="successmsg" role="alert">
        {{Session::get('success') }}
    </div>
@endif
@if (Session::has('error'))
    <div class="alert alert-danger" role="alert">
        {{Session::get('error') }}
    </div>
@endif
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3" style="gap:10px;">
                        <div class="col-12 col-sm-2">
                            <a href="" class="btn btn-cus">Theme Config</a>
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.field')
                                <a href="{{ route('admin.config-field') }}" class="btn btn-cus">Field Config</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.onboarding')
                                <a href="{{ route('admin.config-onboarding') }}" class="btn btn-cus active">OnBoarding Config</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.proposal')
                                <a href="{{route('admin.config-proposal-validation')}}" class="btn btn-cus ">Proposal Validation</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.OTP')
                                <a href="{{ route('admin.config-otp') }}" class="btn btn-cus">OTP Config</a>
                            @endcan
                        </div>

                    </div>
                    <h5 class="card-title">Onboarding Config </h5>

                    <form id="addPorp" action="{{route('admin.onboardingConfig-fetch')}}" method="GET">
                        @csrf
                        <hr>
                        <div class="row align-items-center">
                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12 ">
                                <div class="py-2">
                                    <label>Broker Name</label>

                                    <select autocomplete="none" name="broker"class="form-control ">
                                        <option value="">select Broker</option>
                                        @foreach ( $broker as $dat)
                                        <option value="{{$dat['name']}}">{{$dat['name']}}</option>
                                        @endforeach
                                    </select>
                                    @if (Session::has('validatorerror'))
                                        <p class="text-danger">
                                            {{Session::get('validatorerror') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <button type="submit"  class="view btn btn-primary">Fetch</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
<script>
    // Automatically remove the message after 5 seconds (5000 milliseconds)
    setTimeout(function() {
        document.querySelector('#successmsg').remove();
    }, 5000);
</script>
@endsection('scripts') --}}
