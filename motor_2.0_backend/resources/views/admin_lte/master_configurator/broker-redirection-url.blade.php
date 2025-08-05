@php
$data['logo_url'] = $brokerConfigAsset->where('key', 'logo_url')->pluck('value.url')->first();
$data['success_payment_url_redirection'] = $brokerConfigAsset->where('key', 'success_payment_url_redirection')->pluck('value.url')->first();
$data['other_failure_url']  = $brokerConfigAsset->where('key', 'other_failure_url')->pluck('value.url')->first(); 
@endphp

<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
            <h4 class="form-tab">Broker Url Redirection</h4>
                <!-- <div class="row"> -->
                    <form action="{{route('admin.config-onboarding.broker-url-redirection')}}" method="POST" id="mybrokerform" class="form-submit">
                        @csrf
                        
                        <!-- New checkboxes for each section -->
                        <div class="row">
                            <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2">
                                    <div class="chkbox-cus">
                                        <input
                                            type="checkbox"
                                            id="logo_url"
                                            name="logo_url"
                                            value="true"
                                            {{ isset($data['logo_url']) && !empty($data['logo_url']) && $data['logo_url'] != 'false' ? 'checked' : '' }}
                                        />
                                        <label class="form-check-label" for="logo_url">
                                            Enable Logo URL
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2">
                                    <div class="chkbox-cus">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="success_payment_url_redirection"
                                            name="success_payment_url_redirection"
                                            value="true"
                                            {{ isset($data['success_payment_url_redirection']) && !empty($data['success_payment_url_redirection']) && $data['success_payment_url_redirection'] != 'false' ? 'checked' : '' }}
                                        />
                                        <label class="form-check-label" for="success_payment_url_redirection">
                                            Enable Successful Payment Redirection URL
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2">
                                    <div class="chkbox-cus">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="other_failure_url"
                                            name="other_failure_url"
                                            value="true"
                                            {{ isset($data['other_failure_url']) && !empty($data['other_failure_url']) && $data['other_failure_url'] != 'false' ? 'checked' : '' }}
                                        />
                                        <label class="form-check-label" for="other_failure_url">
                                            Enable Other Failure URL
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sections to show/hide based on checkboxes -->
                        <div class="row">
                            <div class="logo_url card shadow col-12 mb-3" style="background:#F4F5F7;">
                                <div class="p-2">
                                    <label>Logo URL</label>
                                    <textarea autocomplete="none" name="logo_url" placeholder="Logo Url" class="form-control form-control-sm">{{ $data['logo_url'] }}</textarea>
                                </div>
                            </div>
    
                            <div class="success_payment_url_redirection card shadow col-12 mb-3" style="background:#F4F5F7;">
                                <div class="p-2">
                                    <label>Successful Payment Redirection URL</label>
                                    <textarea autocomplete="none" name="success_payment_url_redirection" placeholder="Success Payment Redirection URL" class="form-control form-control-sm">{{ $data['success_payment_url_redirection'] }}</textarea>
                                </div>
                            </div>
    
                            <div class="other_failure_url card shadow col-12 mb-3" style="background:#F4F5F7;">
                                <div class="p-2">
                                    <label>Other Failure URL</label>
                                    <textarea autocomplete="none" name="other_failure_url" placeholder="Other Failure URL" class="form-control form-control-sm">{{ $data['other_failure_url'] }}</textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </div>
                        </div>
                    </form>
                <!-- </div> -->
            </div>
        </div>
    </div>
</div>

<script src="{{asset('js/jquery-3.5.1.min.js')}}"></script>

<script src="{{asset('admin1/js/broker-config/broker-url-redirect.js')}}"></script>