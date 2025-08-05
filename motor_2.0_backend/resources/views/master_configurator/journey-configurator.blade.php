<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <h4 class="form-tab">Journey Configurator</h4>
                    <form action="{{ route('admin.config-onboarding.journey-configurator') }}" method="POST" id="mybrokerform" class="form-submit">
                        @csrf
                        
                        <div class="row">
                            <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2">
                                    <div class="chkbox-cus">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="enableCheckbox"
                                            name="enable"
                                            value="true"
                                        />
                                        <label class="form-check-label" for="enableCheckbox">
                                            Enable Journey Configuration
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="logo_url card shadow mb-3" style="background: #F4F5F7; display: none;" id="extraOptions">
                            
                            <div class="p-2">
                                <label>
                                    <input type="checkbox" name="options[]" value="B2B" checked> B2B
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="options[]" value="B2C" checked> B2C
                                </label>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/jquery-3.5.1.min.js') }}"></script>
<script src="{{ asset('admin1/js/broker-config/journey-configurator.js') }}"></script>
