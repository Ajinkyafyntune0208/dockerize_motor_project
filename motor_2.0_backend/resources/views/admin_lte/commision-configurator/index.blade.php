@extends('admin_lte.layout.app', ['activePage' => 'discount-configurations', 'titlePage' => __('Commision
Configurator')])

@section('content')
<style>
    .btn-cus {
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

    .btn-cus:hover {
        background-color: var(--primary);
        color: white;
    }

    .btn-cus.active {
        background-color: var(--primary);
        color: white;
    }
</style>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4 text-center">
        @if (session('error'))
        <div class="alert alert-danger  py-1">
            {{ session('error') }}
        </div>
        @endif

        @if (session('success'))
        <div class="alert alert-success text-center py-1">
            {{ session('success') }}
        </div>
        @endif
    </div>
</div>

{{-- broker --}}
<div class="card">
    <div class="card-body">
        <div class="row mb-3 mt-4">
            <div class="col-12 col-sm-3">
                @can('configurator.field')
                <a href="{{ route('admin.ic-config.commision-configurator.index', ['ruleType' => 'BROKEX']) }}" class="btn btn-cus {{request('ruleType') == 'API' ? '' : 'active'}}">Brokex Approach</a>
                @endcan
            </div>
            <div class="col-12 col-sm-3">
                @can('configurator.onboarding')
                <a href="{{ route('admin.ic-config.commision-configurator.index', ['ruleType' => 'API']) }}" class="btn btn-cus {{request('ruleType') == 'API' ? 'active' : ''}}">Api Approach</a>
                @endcan
            </div>

        </div>

        <form action="" method="POST">
            @csrf
            <input type="hidden" name="ruleType" value="{{request('ruleType') == 'API' ? 'API' : 'BROKEX'}}">
            <div class="row">
                <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                    <label for="enableCommission" class="required">Enable Commission</label>
                    <select name="enableCommission" id="enableCommission" class="form-control">
                        <option value="Y" {{ old('enableCommission', $configs['enableCommission'] ?? '') == 'Y' ? 'selected' : ''}}>Enable</option>
                        <option value="N" {{ old('enableCommission', $configs['enableCommission'] ?? '') == 'Y' ? '' : 'selected'}}>Disable</option>
                    </select>
                </div>
                <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                    <label for="payInAllowed" class="required">Pay In</label>
                    <select name="payInAllowed" id="payInAllowed" class="form-control">
                        <option value="" disabled>select</option>
                        <option value="Y" {{ old('payInAllowed', $configs['payInAllowed'] ?? '') == 'Y' ? 'selected' : ''}}>Enable</option>
                        <option value="N" {{ old('payInAllowed', $configs['payInAllowed'] ?? '') == 'Y' ? '' : 'selected'}}>Disable</option>
                    </select>
                </div>
                
                <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                    <label for="rewardType" class="required">Reward Type</label>
                    <select name="rewardType" id="rewardType" class="form-control">
                        <option value="" disabled selected>select</option>
                        @foreach ($rewardTypes as $type)
                            <option value="{{$type}}" {{ old('rewardType', $configs['rewardType'] ?? '') == $type ? 'selected' : ''}}>{{$type}}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                    <label for="quoteShare" class="required">Quote Share</label>
                    <select name="quoteShare" id="quoteShare" class="form-control">
                        <option value="" disabled selected>select</option>
                        <option value="Y" {{ old('quoteShare', $configs['quoteShare'] ?? '') == 'Y' ? 'selected' : ''}}>Enable</option>
                        <option value="N" {{ old('quoteShare', $configs['quoteShare'] ?? '') == 'Y' ? '' : 'selected'}}>Disable</option>
                    </select>
                </div>

                @if (request('ruleType') == 'API')
                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="isB2cAllowed" class="required">Allow B2C</label>
                        <select name="isB2cAllowed" id="isB2cAllowed" class="form-control">
                            <option value="" disabled selected>select</option>
                            <option value="Y" {{ old('isB2cAllowed', $configs['isB2cAllowed'] ?? '') == 'Y' ? '' : 'selected'}}>Enable</option>
                            <option value="N" {{ old('isB2cAllowed', $configs['isB2cAllowed'] ?? '') == 'Y' ? 'selected' : ''}}>Disable</option>
                        </select>
                    </div>
                @else
                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="retrospectiveSchedular" class="required">Retrospective Schedular</label>
                        <select name="retrospectiveSchedular" id="retrospectiveSchedular" class="form-control">
                            <option value="" disabled selected>select</option>
                            <option value="Y"{{ old('retrospectiveSchedular', $configs['retrospectiveSchedular'] ?? '') == 'Y' ? 'selected' : ''}}>Enable</option>
                            <option value="N"{{ old('retrospectiveSchedular', $configs['retrospectiveSchedular'] ?? '') == 'Y' ? '' : 'selected'}}>Disable</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="brokerId" class="required">Broker Id</label>
                        <input type="text" id="brokerId" class="form-control" name="brokerId" value="{{old('brokerId', $configs['brokerId'])}}" required>
                    </div>
                @endif
               
            </div>
            <hr>

            @if (request('ruleType') != 'API')
            <h6 class="my-3">Data Base Configuration</h6>
                <div class="row">
                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="databaseDriver" class="required">Driver</label>
                        <input type="text" id="databaseDriver" class="form-control" name="databaseDriver" value="{{old('databaseDriver', $configs['databaseDriver'])}}" required>
                    </div>

                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="databaseHost" class="required">Host</label>
                        <input type="text" id="databaseHost" class="form-control" name="databaseHost" value="{{old('databaseHost', $configs['databaseHost'])}}" required>
                    </div>

                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="databasePort" class="required">Port</label>
                        <input type="text" id="databasePort" class="form-control" name="databasePort" value="{{old('databasePort', $configs['databasePort'])}}" required>
                    </div>

                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="databaseUserName" class="required">User Name</label>
                        <input type="text" id="databaseUserName" class="form-control" name="databaseUserName" value="{{old('databaseUserName', $configs['databaseUserName'])}}" required>
                    </div>

                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="databasePassword" class="required">Password</label>
                        <input type="text" id="databasePassword" class="form-control" name="databasePassword" value="{{old('databasePassword', $configs['databasePassword'])}}" required>
                    </div>

                    <div class="col-md-6 col-lg-4 col-xl-3 form-group">
                        <label for="databaseName" class="required">Database</label>
                        <input type="text" id="databaseName" class="form-control" name="databaseName" value="{{old('databaseName', $configs['databaseName'])}}" required>
                    </div>
                </div>
            @endif

            @if (request('ruleType') == 'API')
            <div class="row">
                <div class="col-12">
                    <small><b>Note : </b> Please setup api related configs in the Third Party Settings.</small>
                </div>
            </div>
            @endif

            <div class="row justify-content-end">
                <div class="col-md-2">
                    <input type="submit" class="form-control btn btn-success btn-rounded" value="Save">
                </div>
            </div>
        </form>
    </div>
</div>
@endsection