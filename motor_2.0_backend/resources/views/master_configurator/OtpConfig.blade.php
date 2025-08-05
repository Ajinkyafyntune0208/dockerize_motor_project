@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Proposal Validation')])
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
</style>
<div class="content-wrapper">

    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                <h5 class="card-title">Master Configurator</h5>
                    <div class="row mb-3" style="gap:10px;">
                        {{-- <div class="col-12 col-sm-2">
                            <a href="" class="btn btn-cus">Theme Config</a>
                        </div> --}}
                        <div class="col-12 col-sm-2">
                            @can('configurator.field')
                                <a href="{{ route('admin.config-field') }}" class="btn btn-cus">Field Config</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.onboarding')
                                <a href="{{ route('admin.config-onboarding') }}" class="btn btn-cus">OnBoarding Config</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.proposal')
                                <a href="{{route('admin.config-proposal-validation')}}" class="btn btn-cus ">Proposal Validation</a>
                            @endcan
                        </div>
                        <div class="col-12 col-sm-2">
                            @can('configurator.OTP')
                                <a href="{{ route('admin.config-otp') }}" class="btn btn-cus active">OTP Config</a>
                            @endcan
                        </div>

                    </div>

                    {{-- <h5 class="card-title">Proposal Validation
                        <button id ="reset" class="view btn btn-primary float-end btn-sm">Reset</button>
                    </h5>
                    <form id="addPorp" method="POST">
                        @csrf

                        @php
                            function fil($string)
                            {
                                $words = explode('_', $string);
                                $titleCaseWords = array_map('ucfirst', $words);
                                return implode(' ', $titleCaseWords);
                            }
                        @endphp
                        <div class="row">
                            <div class="col-12 col-sm-6 form-group">
                                <label for="selectIC">Select IC</label>
                                <select  class="form-control" id="selectIC" name="selectIC" >
                                    <option value="" selected>Select a IC </option>
                                    <option value="all" >All</option>
                                    @foreach ($comp as $dat )
                                        <option value="{{$dat}}">{{fil($dat)}}</option>
                                    @endforeach
                                </select>
                                <sub  class=" selectIcerror text-danger" style="display: none;">Please Select a Ic!</sub>
                            </div>
                            <div class="col-12 col-sm-6 form-group">
                                <label for="journeytype">Select New/Rollover</label>
                                <select class="form-control" id="journeytype">
                                    <option value="" selected>Select type </option>
                                    <option value="NEW">New</option>
                                    <option value="Rollover">Rollover</option>
                                </select>
                                <sub  class=" selectJtypeerror text-danger" style="display: none;">Please Select Journey Type!</sub>

                            </div>

                        </div>
                        <h4>Engine Number</h4>
                        <div class="row">
                            <div class="col-12 col-sm-4 form-group">
                                <label for="minengine">Min</label>
                                <input class="form-control" type="number"  maxlength="10" name="minengine" id="minengine">
                                <sub  class=" minerror text-danger" style="display: none;">Minimum number is required!</sub>
                                <sub  class=" inputerror text-danger" style="display: none;">Minimum should be less than maxiumum!</sub>
                            </div>
                            <div class="col-12 col-sm-4 form-group">
                                <label for="maxengine">Max</label>
                                <input class="form-control" type="number" min="1" name="maxengine" id="maxengine" >
                                <sub  class=" maxerror text-danger" style="display: none;">Maximum number is required!</sub>
                            </div>
                            <div class="col-12 col-sm-4 form-group">
                                <label for="regxengine">Regular Expression <span style="font-size: 9px;">RegEx</span></label>
                                <input class="form-control" type="text" name="regxengine" id="regxengine" >

                            </div>
                        </div>
                        <h4>Chassis Number</h4>
                        <div class="row">
                            <div class="col-12 col-sm-4 form-group">
                                <label for="minchassis">Min</label>
                                <input class="form-control" type="number"  maxlength="10" name="minchassis" id="minchassis" >
                                <sub  class=" minerror text-danger" style="display: none;">Minimum number is required!</sub>
                                <sub  class=" inputerror text-danger" style="display: none;">Minimum should be less than maxiumum!</sub>
                            </div>
                            <div class="col-12 col-sm-4 form-group">
                                <label for="maxchassis">Max</label>
                                <input class="form-control" type="number" min="1" name="maxchassis" id="maxchassis" >
                                <sub  class=" maxerror text-danger" style="display: none;">Maximum number is required!</sub>
                            </div>
                            <div class="col-12 col-sm-4 form-group">
                                <label for="regxchassis">Regular Expression <span style="font-size: 9px;">RegEx</span>></label>
                                <input class="form-control" type="text" name="regxchassis" id="regxchassis" >
                            </div>
                        </div>

                        <button type="button" id="submit" class="btn btn-primary">Submit</button>
                    </form> --}}
                    <div class="container-fluid d-flex justify-content-center align-items-center" style="height: 300px;">
                        <h1 class="text-success">In Development</h1>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/jquery-3.7.0.min.js') }}" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>



@endsection
