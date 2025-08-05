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
    .btn-cus-v2{
        background-color: rgb(255, 255, 255);
        color: var(--primary) !important;
        border: 1px solid black;
        border-radius: 10px;
        height: 44px;
    }
    .btn-cus-v2:hover , .btn-cus-v2:focus{
        background: #dfdfdf;
        color: rgb(0, 0, 0) !important;
        outline: none;
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
    .form-tab{
        padding: 10px;
        background-color: var(--primary);
        color:#fdfdfd;
        border: 1px solid var(--primary);
        border-radius:5px;
        margin-top: 20px;
    }
    .btn-primary:hover ,.btn-warning:hover{
        transform: scale(1.10);
        transition: all .15s;
        box-shadow: rgba(0, 0, 0, 0.452) 0 8px 15px;

    }
    input[type='radio'] {
        accent-color: red;
    }
    .btn-warning{
        background:#e71715 !important;
        outline: none;
        border: none;
    }
    .info-tab{
        background: #d9eefcc9;
        color:#1f3bb3;
        font-weight: bold;
    }
</style>

<div class="content-wrapper">

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
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
                    <div class="row align-items-center">
                        <div class="col-12 col-sm-6">
                            <h5>Details showing for: <span style="color: red;"> {{$bname=='Current' ? 'Current Broker' : $bname }}</span></h5>
                        </div>

                        <div class="col-12 col-sm-6 d-flex gap-3 justify-content-end">
                            @if ( $bname !=='Current')
                                <a href="{{ route('admin.config-onboarding') }}" class="btn btn-primary view ">Fetch for another broker</a>
                            @endif
                            @if ( isset($error))
                                <a type="button" href="{{route('admin.config-onboarding')}}" class="btn btn-warning">Retry</a>
                            @else
                                <a type="button" href="{{route('admin.config-onboarding')}}" class="btn btn-warning" onclick="return confirm('Are you sure you want to cancel?');">Cancel</a>
                            @endif

                        </div>


                    </div>
                    @if (Session::has('success'))
                        <div class="alert alert-success mt-3" id="successmsg" role="alert">
                            {{Session::get('success') }}
                        </div>
                    @endif
                    @if (Session::has('error') || isset($error))
                        <div class="alert alert-danger mt-3" role="alert">
                            {{Session::get('error') ?? $error }}
                        </div>
                    @endif
                    @if (isset($warning))
                        <div class="alert alert-danger mt-3" role="alert">
                            {{$warning}}
                        </div>
                    @endif
                    @if (!isset($error))
                        <p class="alert info-tab mt-3 "> <i class="ti-info-alt"></i>Disclaimer: Changes made to the footer details of some brokers may not necessarily reflect on their websites due to special customized footer. </p>
                        <form  action="{{route('admin.onboardingConfig-store',['broker'=>$bname])}}" method="POST" id="mybrokerform" class="form-submit">
                            @csrf

                            <h4 class="form-tab">Footer/Broker Details</h4>
                            <div class="row">
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class ="required">IRDA No.</label>
                                        <input autocomplete="none"
                                        name="irdanumber" placeholder="Enter IRDA No." type="text"
                                        value="{{isset($data['irdanumber']) ? $data['irdanumber'] : ''}}"
                                        class="form-control form-control-sm" required/>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class ="required">CIN No.</label
                                        ><input
                                        autocomplete="none"
                                        name="cinnumber"
                                        placeholder="Enter CIN No."
                                        type="text"
                                        class="form-control form-control-sm"
                                        value="{{isset($data['cinnumber']) ? $data['cinnumber'] : ''}}" required
                                        />
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">Broker Name in Footer</label
                                        ><input
                                        autocomplete="none"
                                        name="BrokerName"
                                        placeholder="Enter Broker Name"
                                        type="text"
                                        class="form-control form-control-sm"
                                        value="{{isset($data['BrokerName']) ? $data['BrokerName'] : ''}}"
                                        required/>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">Broker Category</label
                                        ><input
                                        autocomplete="none"
                                        name="BrokerCategory"
                                        placeholder="Enter Broker Category"
                                        type="text"
                                        class="form-control form-control-sm"
                                        value="{{isset($data['BrokerCategory']) ? $data['BrokerCategory'] : ''}}"
                                        required/>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">Email</label
                                        ><input
                                        autocomplete="none"
                                        name="email"
                                        placeholder="Enter Email"
                                        type="text"
                                        class="form-control form-control-sm"
                                        value="{{isset($data['email']) ? $data['email'] : ''}}"
                                        required/>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">Phone</label
                                        ><input
                                        autocomplete="none"
                                        name="phone"
                                        placeholder="Enter Phone"
                                        type="text"
                                        class="form-control form-control-sm"
                                        value="{{isset($data['phone']) ? $data['phone'] : ''}}"
                                        required/>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">Broker Support Email</label
                                        ><input
                                        autocomplete="none"
                                        name="brokerSupportEmail"
                                        placeholder="Enter Support Email"
                                        type="text"
                                        class="form-control form-control-sm"
                                        value="{{isset($data['brokerSupportEmail']) ? $data['brokerSupportEmail'] : ''}}"
                                        required/>
                                    </div>
                                </div>
                            </div>

                            <h4 class="form-tab" >Lead Page</h4>
                            <div class="row">
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2">
                                    <label class="required">Lead Page Title Text</label
                                    ><input
                                    autocomplete="none"
                                    name="lead_page_title"
                                    placeholder="enter Title Text"
                                    type="text"
                                    class="form-control form-control-sm"
                                    value="{{isset($data['lead_page_title']) ? $data['lead_page_title'] : ''}}"
                                    required/>
                                </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2">
                                    <label class="required">Mobile Lead Page Title Text</label
                                    ><input
                                    autocomplete="none"
                                    name="mobile_lead_page_title"
                                    placeholder="enter Title Text"
                                    type="text"
                                    class="form-control form-control-sm"
                                    value="{{isset($data['mobile_lead_page_title']) ? $data['mobile_lead_page_title'] : ''}}"
                                    required/>
                                </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2">
                                    <label class="required">Want to show Renewal option?</label>
                                    <div class="rad-cus">
                                    <input
                                        type="radio"
                                        name="renewal"
                                        id="renewal-Yes"
                                        value="Yes"
                                        {{ isset($data['renewal']) ? (($data['renewal'] == 'Yes') ? 'checked' : '') : '' }}
                                    required/><label for="renewal-Yes">Yes</label
                                    ><input
                                        type="radio"
                                        name="renewal"
                                        id="renewal-No"
                                        value="No"
                                        {{ isset($data['renewal']) ? (($data['renewal'] == 'No') ? 'checked' : '') : '' }}
                                    /><label for="renewal-No">No</label>
                                    </div>
                                </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2 ">
                                    <label></label>
                                    <div class="sc-dExYaf iSzLhg">
                                        <div class="chkbox-cus">
                                            <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="noBack"
                                            name="noBack"
                                            value="true"
                                            {{ isset($data['noBack']) ? ( (!empty($data['noBack'] ) && $data['noBack'] != 'false' ) ? 'checked' : '') : ''}}
                                            /><label class="form-check-label" for="noBack"
                                            >Disable lead-page in B2B journey
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2 ">
                                    <label></label>
                                    <div class="chkbox-cus">
                                        <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="fullName"
                                        name="fullName"
                                        value="true"
                                        {{ isset($data['fullName']) ? ( (!empty($data['fullName'] ) && ($data['fullName'] != 'false')) ? 'checked' : '') :''}}
                                        /><label class="form-check-label" for="fullName"
                                        >Mandatory lead name.
                                        </label>
                                    </div>
                                </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2 ">
                                    <div class="chkbox-cus">
                                        <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="mobileNo"
                                        name="mobileNo"
                                        value="true"
                                        {{ isset($data['mobileNo']) ? (( !empty($data['mobileNo'] ) && ($data['mobileNo'] != 'false')) ? 'checked' : ''):'' }}
                                        /><label class="form-check-label" for="mobileNo"
                                        >Mandatory lead contact number.
                                        </label>
                                    </div>
                                </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2 ">
                                    <div class="chkbox-cus">
                                        <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="lead_email"
                                        name="lead_email"
                                        value="true"
                                        {{ isset($data['lead_email']) ? ( (!empty($data['lead_email'] ) && ($data['lead_email'] != 'false')) ? 'checked' : '' ) :'' }}
                                        /><label class="form-check-label" for="lead_email"
                                        >Mandatory lead email.
                                        </label>
                                    </div>
                                </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                <div class="py-2 ">
                                    <div class="chkbox-cus">
                                        <input
                                        type="checkbox"
                                        class="form-check-input"
                                        id="lead_otp"
                                        name="lead_otp"
                                        value="true"
                                        {{ isset($data['lead_otp']) ? ( (!empty($data['lead_otp'] ) && ($data['lead_otp'] != 'false') ) ? 'checked' : '' ) : '' }}
                                        /><label class="form-check-label" for="lead_otp"
                                        >Enable Lead Page Otp
                                        </label>
                                    </div>
                                </div>
                                </div>
                            </div>

                            <h4 class="form-tab">Quotes Page</h4>
                            <div>
                                <div class="row">
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                    <label class="required">Inactivity timeout</label
                                    ><input
                                        autocomplete="none"
                                        name="time_out"
                                        placeholder="Enter Time in Minutes"
                                        type="text"
                                        class="form-control form-control-sm"
                                        value="{{isset($data['time_out']) ? $data['time_out'] : ''}}"
                                    required/>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">GST default selected</label>
                                        <div class="rad-cus">
                                            <input type="radio" name="gst"
                                                id="gst-Yes" value="Yes"
                                                {{ isset($data['gst']) ? (($data['gst'] == 'Yes' ) ? 'checked' : ''):'' }}
                                            required/>
                                            <label for="gst-Yes" >Yes</label>
                                            <input type="radio" name="gst"
                                                id="gst-No" value="No"
                                                {{ isset($data['gst']) ? (($data['gst'] == 'No' ) ? 'checked' : ''):'' }}
                                            />
                                            <label for="gst-No" >No</label
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                    <label class="required">PYP insurer prompt on load</label>
                                    <div class="rad-cus">
                                        <input
                                        type="radio"
                                        name="ncbconfig"
                                        id="ncbconfig-Yes"
                                        value="Yes"
                                        {{ isset($data['ncbconfig']) ? (($data['ncbconfig'] == 'Yes') ? 'checked' : ''):'' }}
                                        /><label for="ncbconfig-Yes">Yes</label
                                        ><input
                                        type="radio"
                                        name="ncbconfig"
                                        id="ncbconfig-No"
                                        value="No"
                                        {{ isset($data['ncbconfig']) ? (($data['ncbconfig'] == 'No' ) ? 'checked' : ''):'' }}
                                        /><label for="ncbconfig-No">No</label>
                                    </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">CPA default selected</label>
                                        <div class="rad-cus">
                                            <input type="radio" name="cpa"
                                            id="cpa-Yes" value="Yes"
                                            {{ isset($data['cpa']) ? (($data['cpa'] == 'Yes' ) ? 'checked' : ''):'' }}
                                            />
                                            <label for="cpa-Yes">Yes</label>
                                            <input type="radio" name="cpa" id="cpa-No" value="No"
                                                {{ isset($data['cpa']) ? (($data['cpa'] == 'No' ) ? 'checked' : ''):'' }}
                                            />
                                            <label for="cpa-No">No</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                    <label class="required">Select Multi Year CPA</label>
                                    <div class="rad-cus">
                                        <input
                                        type="radio"
                                        name="multicpa"
                                        id="multicpa-Yes"
                                        value="Yes"
                                        {{ isset($data['multicpa']) ? (($data['multicpa'] == 'Yes') ? 'checked' : ''):'' }}
                                        /><label for="multicpa-Yes">Yes</label
                                        ><input
                                        type="radio"
                                        name="multicpa"
                                        id="multicpa-No"
                                        value="No"
                                        {{ isset($data['multicpa']) ? (($data['multicpa'] == 'No' ) ? 'checked' : ''):'' }}
                                        /><label for="multicpa-No">No</label>
                                    </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                    <label class="required">Select Card Option</label>
                                    <div class="rad-cus">
                                        <input
                                        type="radio"
                                        name="quoteview"
                                        id="quoteview-grid"
                                        value="grid"
                                        {{ isset($data['quoteview']) ? (($data['quoteview'] == 'grid' ) ? 'checked' : ''):'' }}
                                        /><label for="quoteview-grid">Grid view</label
                                        ><input
                                        type="radio"
                                        name="quoteview"
                                        id="quoteview-list"
                                        value="list"
                                        {{ isset($data['quoteview']) ? (($data['quoteview'] == 'list' ) ? 'checked' : ''):'' }}
                                        /><label for="quoteview-list">List View</label>
                                    </div>
                                    </div>
                                </div>
                                </div>
                                <div class="row">
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                    <label class="required">GST Toggle Style</label>
                                    <div class="rad-cus">
                                        <input
                                        type="radio"
                                        name="gst_style"
                                        id="gst_style-fromTheme"
                                        value="fromTheme"
                                        {{ isset($data['gst_style']) ? (($data['gst_style'] == 'fromTheme' ) ? 'checked' : ''):'' }}
                                        /><label for="gst_style-fromTheme">From Theme</label
                                        ><input
                                        type="radio"
                                        name="gst_style"
                                        id="gst_style-notFromTheme"
                                        value="notFromTheme"
                                        {{ isset($data['gst_style']) ? (($data['gst_style'] == 'notFromTheme' ) ? 'checked' : ''):'' }}
                                        /><label for="gst_style-notFromTheme">Select Other</label>
                                    </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">50 Lakh convert to NON POS ?</label>
                                        <div class="rad-cus">
                                            <input type="radio" name="fiftyLakhNonPos" id="fifty_lakh_non_pos_yes" value="yes"
                                            {{ isset($data['fiftyLakhNonPos']) ? (($data['fiftyLakhNonPos'] == 'yes' ) ? 'checked' : ''):'' }}/>
                                            <label for="fifty_lakh_non_pos_yes">Yes</label>
                                            <input type="radio" name="fiftyLakhNonPos" id="fifty_lakh_non_pos_no" value="no"
                                            {{ isset($data['fiftyLakhNonPos']) ? (($data['fiftyLakhNonPos'] == 'no' ) ? 'checked' : ''):'' }}/>
                                            <label for="fifty_lakh_non_pos_no">No</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">Enable 3 Month Short-term ?</label>
                                        <div class="rad-cus">
                                            <input type="radio" name="threeMonthShortTermEnable" id="three_month_short_term_enable_yes" value="yes"
                                            {{ isset($data['threeMonthShortTermEnable']) ? (($data['threeMonthShortTermEnable'] == 'yes' ) ? 'checked' : ''):'' }}/>
                                            <label for="three_month_short_term_enable_yes">Yes</label>
                                            <input type="radio" name="threeMonthShortTermEnable" id="three_month_short_term_enable_no" value="no"
                                            {{ isset($data['threeMonthShortTermEnable']) ? (($data['threeMonthShortTermEnable'] == 'no' ) ? 'checked' : ''):'' }}/>
                                            <label for="three_month_short_term_enable_no">No</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2">
                                        <label class="required">Enable 6 Month Short-term ?</label>
                                        <div class="rad-cus">
                                            <input type="radio" name="sixMonthShortTermEnable" id="six_month_short_term_enable_yes" value="yes"
                                            {{ isset($data['sixMonthShortTermEnable']) ? (($data['sixMonthShortTermEnable'] == 'yes' ) ? 'checked' : ''):'' }}/>
                                            <label for="six_month_short_term_enable_yes">Yes</label>
                                            <input type="radio" name="sixMonthShortTermEnable" id="six_month_short_term_enable_no" value="no"
                                            {{ isset($data['sixMonthShortTermEnable']) ? (($data['sixMonthShortTermEnable'] == 'no' ) ? 'checked' : ''):'' }}/>
                                            <label for="six_month_short_term_enable_no">No</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="gst_style_fields card shadow mb-3" style="background:#F4F5F7;">
                                    <div class="row">
                                        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="py-2">
                                              <label>GST Text Color</label
                                              ><input
                                                autocomplete="none"
                                                name="gst_text_color"
                                                placeholder="enter color in hexa-decimal"
                                                type="color"
                                                class="form-control form-control-sm"
                                                style="padding: 0px"
                                                value="{{ isset($data['gst_text_color']) ? $data['gst_text_color']:'#000000' }}"
                                              />
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="py-2">
                                              <label>GST Color (YES)</label
                                              ><input
                                                autocomplete="none"
                                                name="gst_color"
                                                placeholder="enter color in hexa-decimal"
                                                type="color"
                                                class="form-control form-control-sm"
                                                style="padding: 0px"
                                                value="{{ isset($data['gst_color']) ? $data['gst_color']:'#000000' }}"
                                              />
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="py-2">
                                              <label>GST Color (NO)</label
                                              ><input
                                                autocomplete="none"
                                                name="gst_color_no"
                                                placeholder="enter color in hexa-decimal"
                                                type="color"
                                                class="form-control form-control-sm"
                                                style="padding: 0px"
                                                value="{{ isset($data['gst_color_no']) ? $data['gst_color_no']:'#000000' }}"
                                              />
                                            </div>
                                          </div>
                                        </div>

                                    </div>

                                </div>
                            </div>

                            <h4 class="form-tab">Proposal Page</h4>
                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
                                <div class="py-2">
                                    <label class="required">Proposal Page Declaration Text</label
                                    ><textarea
                                    autocomplete="none"
                                    name="p_declaration"
                                    placeholder="Proposal Declaration Text"
                                    type="text"
                                    class="form-control form-control-sm"
                                    required>{{isset($data['p_declaration']) ? $data['p_declaration']: '' }}</textarea>
                                </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2 ">
                                        <div class="chkbox-cus">
                                            <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="vahan_err"
                                            name="vahan_err"
                                            value="true"
                                            {{ isset($data['vahan_err']) ? ( ( !empty($data['vahan_err'] ) && ($data['vahan_err'] != 'false')  ) ? 'checked' : '' ) : '' }}
                                            /><label class="form-check-label" for="vahan_err"
                                            >Custom vahaan error?
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="vahan_err_field card shadow mb-3" style="background:#F4F5F7;">
                                    <div class="p-2">
                                        <label>Custom vahaan error? </label
                                        ><textarea
                                            autocomplete="none"
                                            name="vahan_error"
                                            placeholder="Custom vahaan error "
                                            type="text"
                                            class="form-control form-control-sm"
                                        >{{isset($data['vahan_error']) ? $data['vahan_error']: '' }}</textarea>
                                    </div>
                                </div>
                                <div class="col-xl-5 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2 ">
                                        <div class="chkbox-cus">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                id="allow_multipayment"
                                                name="allow_multipayment"
                                                value="true"
                                                {{ isset($data['allow_multipayment']) ? (( !empty($data['allow_multipayment'] ) && ($data['allow_multipayment'] != 'false')) ? 'checked' : ''): ''}}
                                            /><label class="form-check-label" for="allow_multipayment"
                                                >Block Multi-Payment on single Enquiry Id
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2 ">
                                        <div class="chkbox-cus">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                id="enable_vahan"
                                                name="enable_vahan"
                                                value="true"
                                                {{ isset($data['enable_vahan']) ? (( !empty($data['enable_vahan'] ) && ($data['enable_vahan'] != 'false')) ? 'checked' : ''): ''}}
                                            /><label class="form-check-label" for="enable_vahan"
                                                >Enable Vahan Service
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-5 col-md-8 col-sm-8 col-8">
                                    <div class="py-2 ">
                                        <div class="chkbox-cus">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                id="enableVehicleCategory"
                                                name="enableVehicleCategory"
                                                value="true"
                                                {{ isset($data['enableVehicleCategory']) ? (( !empty($data['enableVehicleCategory'] ) && ($data['enableVehicleCategory'] != 'false')) ? 'checked' : ''): ''}}
                                            /><label class="form-check-label" for="enableVehicleCategory"
                                                >Vehicle Category Validation
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h4 class="form-tab">Popups &amp; Others</h4>
                            <div class="mb-3">
                                <div class="row">
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="ckyc_mandate"
                                                    name="ckyc_mandate"
                                                    value="true"
                                                    {{isset($data['ckyc_mandate']) ? (( !empty($data['ckyc_mandate'] ) && ($data['ckyc_mandate'] != 'false')) ? 'checked' : '' ) : ''}}

                                                /><label class="form-check-label" for="ckyc_mandate"
                                                    >CKYC Mandate Popup?
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mandate-fields card shadow mb-3" style="background:#F4F5F7;">
                                    <div class="row p-3">
                                        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="py-2">
                                                <label>Mandate Popup Title</label
                                                ><input
                                                autocomplete="none"
                                                name="mandate_title"
                                                placeholder="Enter Title Text"
                                                type="text"
                                                class="form-control form-control-sm"
                                                value="{{isset($data['mandate_title']) ? $data['mandate_title'] : ''}}"
                                                />
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="py-2">
                                                <label>Mandate Popup Heading</label
                                                ><input
                                                autocomplete="none"
                                                name="mandate_h"
                                                placeholder="Enter Heading Text"
                                                type="text"
                                                class="form-control form-control-sm"
                                                value="{{ isset($data['mandate_h']) ? $data['mandate_h'] : ''}}"
                                                />
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="py-2">
                                                <label>Mandate Popup First Line</label
                                                ><input
                                                autocomplete="none"
                                                name="mandate_p1"
                                                placeholder="Enter First Line"
                                                type="text"
                                                class="form-control form-control-sm"
                                                value="{{isset($data['mandate_p1']) ? $data['mandate_p1'] : ''}}"
                                                />
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="py-2">
                                                <label>Mandate popup Second Line</label
                                                ><input
                                                autocomplete="none"
                                                name="mandate_p2"
                                                placeholder="Enter Second Line"
                                                type="text"
                                                class="form-control form-control-sm"
                                                value="{{isset($data['mandate_p2']) ? $data['mandate_p2'] : ''}}"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="payment_redirection"
                                                    name="payment_redirection"
                                                    value="true"
                                                    {{ isset($data['payment_redirection']) ? (( !empty($data['payment_redirection'] ) && ($data['payment_redirection'] != 'false')) ? 'checked' : '') : ''}}
                                                /><label class="form-check-label" for="payment_redirection"
                                                    >Customise payment redirection message
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="payment_redirection_fields card shadow mb-3" style="background:#F4F5F7;">
                                        <div class="p-2">
                                            <label>Redirection Message</label>
                                            <textarea
                                                autocomplete="none"
                                                name="payment_redirection_message"
                                                placeholder="Redirection Message"
                                                type="text"
                                                class="form-control form-control-sm"
                                            > {{isset($data['payment_redirection_message']) ? $data['payment_redirection_message'] : ''}}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="ckyc_redirection"
                                                    name="ckyc_redirection"
                                                    value="true"
                                                    {{ isset($data['ckyc_redirection']) ? (( !empty($data['ckyc_redirection'] ) && ($data['ckyc_redirection'] != 'false')) ? 'checked' : '') : ''}}
                                                /><label class="form-check-label" for="ckyc_redirection"
                                                    >Customise CKYC redirection message
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ckyc_redirection_fields card shadow mb-3" style="background:#F4F5F7;">
                                        <div class="p-2">
                                            <label>Redirection Message</label
                                            ><textarea
                                            autocomplete="none"
                                            name="ckyc_redirection_message"
                                            placeholder="Redirection Message"
                                            type="text"
                                            class="form-control form-control-sm"
                                            >{{isset($data['ckyc_redirection_message']) ? $data['ckyc_redirection_message'] : ''}}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="cpaOptOut"
                                                    name="cpaOptOut"
                                                    value="true"
                                                    {{ isset($data['cpaOptOut']) ? (( !empty($data['cpaOptOut'] ) && ($data['cpaOptOut'] != 'false')) ? 'checked' : '') : ''}}
                                                /><label class="form-check-label" for="cpaOptOut"
                                                    >CPA Opt Out Reason
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="cpa_opt_out_fields card shadow mb-3" style="background:#F4F5F7;">
                                        <div class="p-2">

                                            <label class="mb-2">Select Opt-out Reason(s)<span style="color: red;">*</span></label>

                                            <select name="cpaOptOutReasons[]" id="cpaOptOutReasons" data-style="btn-cus-v2"
                                            data-live-search="true" data-actions-box="true" multiple
                                            class="selectpicker w-100"  aria-required="true">
                                                <option value="I have another PA policy with cover amount of INR 15 Lacs or more">I have another PA policy with cover amount of INR 15 Lacs or more</option>
                                                <option value="I do not have a valid driving license.">I do not have a valid driving license.</option>
                                                <option value="I have another motor policy with PA owner driver cover in my name">I have another motor policy with PA owner driver cover in my name</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="fastlane_error"
                                                    name="fastlane_error"
                                                    value="true"
                                                    {{ isset($data['fastlane_error']) ? (( !empty($data['fastlane_error'] ) && ($data['fastlane_error'] != 'false')) ? 'checked' : ''): ''}}

                                                /><label class="form-check-label" for="fastlane_error"
                                                    >Input page vahaan error
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="fastlane_error_fields card shadow mb-3" style="background:#F4F5F7;">
                                        <div class="p-2">
                                            <label> Input page Vahaan Error Message</label
                                            ><textarea
                                            autocomplete="none"
                                            name="fastlane_error_message"
                                            placeholder="Input page Vahaan Error Message"
                                            type="text"
                                            class="form-control form-control-sm"
                                            >{{isset($data['fastlane_error_message']) ? $data['fastlane_error_message'] : ''}}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="journey_block"
                                                    name="journey_block"
                                                    value="true"
                                                    {{ isset($data['journey_block']) ? (( !empty($data['journey_block'] ) && ($data['journey_block'] != 'false')) ? 'checked' : '') : ''}}
                                                /><label class="form-check-label" for="journey_block"
                                                    >Block journey if no record found.
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="showBreadcrumbs"
                                                    name="showBreadcrumbs"
                                                    value="true"
                                                    {{ isset($data['showBreadcrumbs']) ? (( !empty($data['showBreadcrumbs'] ) && ($data['showBreadcrumbs'] != 'false')) ? 'checked' : '') : ''}}
                                                /><label class="form-check-label" for="showBreadcrumbs"
                                                    >Show Breadcrumbs
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="hide_retry"
                                                    name="hide_retry"
                                                    value="true"
                                                    {{ isset($data['hide_retry']) ? (( !empty($data['hide_retry'] ) && ($data['hide_retry'] != 'false')) ? 'checked' : ''): ''}}
                                                /><label class="form-check-label" for="hide_retry"
                                                    >Hide retry after payment failure
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                id="block_home_redirection"
                                                name="block_home_redirection"
                                                value="true"
                                                {{ isset($data['block_home_redirection']) ? (( !empty($data['block_home_redirection'] ) && ($data['block_home_redirection'] != 'false')) ? 'checked' : ''): ''}}
                                            /><label class="form-check-label" for="block_home_redirection"
                                                >Hide 'Go to Home' after payment failure
                                            </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                id="enableMultiLanguages"
                                                name="enableMultiLanguages"
                                                value="true"
                                                {{ isset($data['enableMultiLanguages']) ? (( !empty($data['enableMultiLanguages'] ) && ($data['enableMultiLanguages'] != 'false')) ? 'checked' : ''): ''}}
                                            /><label class="form-check-label" for="enableMultiLanguages "
                                                >Enable Multi-Languages
                                            </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="py-2 ">
                                            <div class="chkbox-cus">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    id="pc_redirection"
                                                    name="pc_redirection"
                                                    value="true"
                                                    {{ isset($data['pc_redirection']) ? (( !empty($data['pc_redirection'] ) && ($data['pc_redirection'] != 'false')) ? 'checked' : ''): ''}}
                                                /><label class="form-check-label" for="pc_redirection"
                                                    >Enable B2B & B2C Redirection
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h4 class="form-tab">Redirection URL</h4>
                            <div class="row">
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2 ">
                                        <div class="chkbox-cus">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="redirection_url_status"
                                            name="redirection_url_status"
                                            value="true"
                                            {{ isset($data['redirection_url_status']) ? (( !empty($data['redirection_url_status'] ) && ($data['redirection_url_status'] != 'false')) ? 'checked' : ''): ''}}
                                        /><label class="form-check-label" for="redirection_url_status"
                                            >Enable Redirection URL
                                        </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="pospRetUrl_fields card shadow mb-3" style="background:#F4F5F7;">
                                    <div class="p-2">
                                        <label>POSP Redirection URL</label>
                                        <textarea autocomplete="none" name="pospRetUrl" placeholder="POSP Redirection URL" type="text" class="form-control form-control-sm"> {{ isset($data['pospRetUrl']) ? $data['pospRetUrl'] : '' }}</textarea>
                                    </div>
                                </div>
                                <div class="employeeRetUrl_fields card shadow mb-3" style="background:#F4F5F7;">
                                    <div class="p-2">
                                        <label>Employee Redirection URL</label>
                                        <textarea autocomplete="none" name="employeeRetUrl" placeholder="Employee Redirection URL" type="text" class="form-control form-control-sm"> {{ isset($data['employeeRetUrl']) ? $data['employeeRetUrl'] : '' }}</textarea>
                                    </div>
                                </div>
                                <div class="b2cRetUrl_fields card shadow mb-3" style="background:#F4F5F7;">
                                    <div class="p-2">
                                        <label>B2C Redirection URL</label>
                                        <textarea autocomplete="none" name="b2cRetUrl" placeholder="B2C Redirection URL" type="text" class="form-control form-control-sm"> {{ isset($data['b2cRetUrl']) ? $data['b2cRetUrl'] : '' }}</textarea>
                                    </div>
                                </div>
                                <div class="b2cDashRetUrl_fields card shadow mb-3" style="background:#F4F5F7;">
                                    <div class="p-2">
                                        <label>B2C from Dashboard Redirection URL</label>
                                        <textarea autocomplete="none" name="b2cDashRetUrl" placeholder="B2C from Dashboard Redirection URL" type="text" class="form-control form-control-sm"> {{ isset($data['b2cDashRetUrl'],)? $data['b2cDashRetUrl']: '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <h4 class="form-tab">Misc</h4>
                            <div class="row" style="align-items: center;">
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2 ">
                                        <div class="chkbox-cus">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="feedbackModule"
                                            name="feedbackModule"
                                            value="true"
                                            {{ isset($data['feedbackModule']) ? (( !empty($data['feedbackModule'] ) && ($data['feedbackModule'] != 'false')) ? 'checked' : ''): ''}}
                                        /><label class="form-check-label" for="feedbackModule "
                                            >Enable Feedback Module
                                        </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                    <div class="py-2 ">
                                        <div class="chkbox-cus">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="consentModule"
                                            name="consentModule"
                                            value="true"
                                            {{ isset($data['consentModule']) ? (( !empty($data['consentModule'] ) && ($data['consentModule'] != 'false')) ? 'checked' : ''): ''}}
                                        /><label class="form-check-label" for="consentModule "
                                            >Enable Consent Module
                                        </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="submit" id="submit" class="btn btn-primary">Submit</button>
                                <a type="button" href="{{route('admin.config-onboarding')}}" class="btn btn-warning" onclick="return confirm('Are you sure you want to cancel?');">Cancel</a>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <h4 class="form-tab">File Type/Size Configurator</h4>
                        <form action="{{route('admin.onboardingConfig.store.fileConfig')}}" method="POST" class="form-submit">
                            @csrf
                            <div>
                                <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="d-flex align-items-center">
                                            <div class="py-2">
                                                <label class="mb-2 required">File Config for IC</label>
                                                <select name="fileConfigIC[]" id="fileConfigIC" data-style="btn-cus-v2" data-live-search="true" data-actions-box="true"class="selectpicker w-100"  aria-required="true" multiple required>
                                                    @foreach ($comp as $dat )
                                                        <option value="{{$dat}}">{{fil($dat)}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="mx-2" style="margin-top: 28px;">
                                                <button type="button" class="btn btn-cus btn-sm" title="View already configured data" data-toggle="modal" data-target="#exampleModalCenter" >
                                                    <i class="fa fa-eye mx-1"></i>
                                                    view
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @php
                                    $extensions = [
                                        ".jpg"  => "JPG",
                                        ".jpeg" => "JPEG",
                                        ".pdf"  => "PDF",
                                        ".png"  => "PNG",
                                        ".bitmap" => "Bitmap",
                                    ];
                                @endphp

                                <div class="accept_fields card shadow mb-3" style="background:#F4F5F7;">
                                    <div class="row">
                                        <div class="col-xl-4 col-lg-12 col-md-12 col-sm-12 col-12">
                                            <div class="py-2">
                                                <label class="mb-2">Allowed extensions<span style="color: red;">*</span></label>

                                                <select name="acceptedExtensions[]" id="acceptedExtensions" data-style="btn-cus-v2"
                                                data-live-search="true" data-actions-box="true" multiple
                                                    class="selectpicker w-100"  aria-required="true">
                                                    @foreach($extensions as $key => $value)
                                                        <option value="{{ $key }}">{{ $value }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="py-2 ">
                                                <label class="mb-2">Max file size allowed<span style="color: red;">*</span></label>
                                                <select name="maxFileSize" id="maxFileSize" data-style="btn-cus-v2"
                                                        data-live-search="true" data-actions-box="true"
                                                    class="selectpicker w-100" aria-required="true">
                                                    <option value="">Nothing selected</option>
                                                    @for ($i=0.5; $i<=5 ; $i+=0.5)
                                                        <option value="{{$i}}*1024*1024">{{$i}} MB</option>
                                                    @endfor
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between" style="margin-bottom: ;">
                                        <button type="submit" id="submit" class="btn btn-primary">Submit</button>
                                    </div>
                                </div>


                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('master_configurator.broker-logo')
    @include('master_configurator.broker-scripts')
    @include('master_configurator.logo-title-description')
    @include('master_configurator.broker-redirection-url')
    @include('master_configurator.journey-configurator')

    @php
        if(isset($data['file_ic_config']) && !empty($data['file_ic_config'])){
            $existingaccept = $data['file_ic_config'];
        }else {
            $existingaccept = null;
        }
        $cpaOptOutReasonsData = isset($data['cpaOptOutReasons']) && !empty($data['cpaOptOutReasons']) ? $data['cpaOptOutReasons']: '';
        function fil($string){
            $words = explode('_', $string);
            $titleCaseWords = array_map('ucfirst', $words);
            return implode(' ', $titleCaseWords);
        }
    @endphp
    <!-- Modal -->
    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title" id="exampleModalCenterTitle">Configured File For IC</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                </div>
                <div class="modal-body">
                    @if (isset($data['file_ic_config']) && $data['file_ic_config'] != null)

                        <table class="table table-bordered table-responsive">
                            <thead>
                                <tr class="text-center">
                                    <td>Sr No.</td>
                                    <td>IC</td>
                                    <td>Accepted Extension</td>
                                    <td>Max File Size</td>
                                </tr>
                            </thead>
                            <tbody class="text-center">
                                @if ( isset($existingaccept) && $existingaccept != null)
                                    @foreach ($existingaccept as $items)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{fil($items['ic'])}}</td>
                                            <td>
                                                @if (isset($items['acceptedExtensions']) && !empty($items['acceptedExtensions']))

                                                    @foreach ($items['acceptedExtensions'] as $ext)
                                                        @foreach ($extensions as $key => $value)
                                                            @if ($ext == $key)
                                                                {{$value.','}}
                                                            @endif
                                                        @endforeach
                                                    @endforeach

                                                @else
                                                    Not Configured
                                                @endif
                                            </td>
                                            <td>
                                                @if (isset($items['maxFileSize']) && !empty($items['maxFileSize']))
                                                    @php
                                                        $size = explode("*", $items['maxFileSize']);
                                                    @endphp
                                                    {{$size[0]}} MB
                                                @else
                                                    Not Configured
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>

                    @else
                        <div class="d-flex justify-content-center align-items-center">
                            <h5>Not Configured yet</h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

<script src="{{ asset('js/jquery-3.7.0.min.js') }}" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
<script>
    let fileicdata;

    if(@json($existingaccept) != null ){
        fileicdata = @json($existingaccept);
    }
    console.log(fileicdata);
    $(document).ready(function() {

        $('input[name=gst_style]').change(function() {
            let chk = $('input[name=gst_style]:checked', '#mybrokerform').val();

            if (chk == 'notFromTheme') {
                $('.gst_style_fields').show();
            } else {
                $('.gst_style_fields').hide();
            }
        });

        let chk2 = $('input[name=gst_style]:checked', '#mybrokerform').val();

        if (chk2 == 'notFromTheme') {
            $('.gst_style_fields').show();
        }else{
            $('.gst_style_fields').hide();
        }

        let cpaOptOut = "{{isset($data['cpaOptOut']) && !empty($data['cpaOptOut']) ? $data['cpaOptOut']: 'false' }}";
        // alert(cpaOptOut);
        if (cpaOptOut != 'false'){
            $('.cpa_opt_out_fields').show();
            $('#cpaOptOutReasons').attr('required', true);
            let cpaOptOutReasonsData = @json($cpaOptOutReasonsData);
            if ( cpaOptOutReasonsData != '' && Array.isArray(cpaOptOutReasonsData) ){
                let cpaOptOutReasons = $('#cpaOptOutReasons');
                cpaOptOutReasons.val(cpaOptOutReasonsData);
                cpaOptOutReasons.selectpicker('refresh');
            }
        }
        $('#fileConfigIC').change(function() {
            let dropdownTitle = ($(this).next().children().children().children().text());
            if(dropdownTitle.length>60){
                let slicedTitle = dropdownTitle.slice(0, 60) + "...";
                $(this).next().children().children().children().html(slicedTitle);
            }
            let selectedIC = $('#fileConfigIC').val();
            let foundIC = false; // Flag to track if the selected IC is found

            if (selectedIC && Array.isArray(fileicdata)) {
                fileicdata.forEach(function(item, index) {
                    if (item['ic'] === selectedIC) {
                        matcheddata = item;
                        foundIC = true;
                        return false;
                    }
                });
            }

            if (selectedIC !== "" && selectedIC !== null) {
                $('.accept_fields').show();

                if (foundIC) {
                    // Fill in the "Allowed extensions" dropdown as a multi-select
                    let acceptedExtensionsSelect = $('#acceptedExtensions');
                    acceptedExtensionsSelect.val(matcheddata.acceptedExtensions);
                    acceptedExtensionsSelect.selectpicker('refresh');

                    // Fill in the "Max file size allowed" dropdown
                    let maxFileSizeSelect = $('#maxFileSize');
                    maxFileSizeSelect.val([matcheddata.maxFileSize]);
                    maxFileSizeSelect.selectpicker('refresh');
                } else {

                    let acceptedExtensionsSelect = $('#acceptedExtensions');
                    acceptedExtensionsSelect.val([]);
                    acceptedExtensionsSelect.selectpicker('refresh');


                    let maxFileSizeSelect = $('#maxFileSize');
                    maxFileSizeSelect.val([]);
                    maxFileSizeSelect.selectpicker('refresh');

                    matcheddata = null;
                }
            } else {
                $('.accept_fields').hide();
            }
        });
        $('.accept_fields').hide();
    })
    // Usage for CKYC Mandate checkbox and fields
    toggleFields('vahan_err', '.vahan_err_field');
    toggleFields('ckyc_mandate', '.mandate-fields');
    toggleFields('payment_redirection', '.payment_redirection_fields');
    toggleFields('ckyc_redirection', '.ckyc_redirection_fields');
    toggleFields('fastlane_error', '.fastlane_error_fields');
    toggleFields('cpaOptOut', '.cpa_opt_out_fields');
    toggleFields('pospRetUrl', '.pospRetUrl_fields');
    toggleFields('employeeRetUrl', '.employeeRetUrl_fields');
    toggleFields('b2cRetUrl', '.b2cRetUrl_fields');
    toggleFields('b2cDashRetUrl', '.b2cDashRetUrl_fields');

    function toggleFields(checkboxId, fieldSelector) {
        const checkbox = document.getElementById(checkboxId);
        const fields = document.querySelectorAll(fieldSelector);

        checkbox.addEventListener('change', function () {
            fields.forEach(field => field.style.display = this.checked ? 'block' : 'none');
        });

        // Initialize visibility based on checkbox state on page load
        fields.forEach(field => field.style.display = checkbox.checked ? 'block' : 'none');
    }

    // Automatically remove the message after 5 seconds (5000 milliseconds)
    setTimeout(function() {
        document.querySelector('#successmsg')?.remove();
    }, 5000);
    let forms =  document.querySelectorAll('.form-submit');
        forms.forEach(element => {
            element.addEventListener('submit', (e) => {
                let check = confirm('Are you sure you want to submit?');
                console.log(check)
                if (!check) {
                    e.preventDefault();
                }
            })
        });


</script>


  <script src="{{ asset('js/popper.min.js') }}" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
  <script src="{{ asset('js/bootstrap.min.js') }}" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>


@endsection
@push('scripts')
    <script>
        $('.pospRetUrl_fields').hide();
        $('.employeeRetUrl_fields').hide();
        $('.b2cRetUrl_fields').hide();
        $('.b2cDashRetUrl_fields').hide();
$(document).ready(function(){
    $('#redirection_url_status').change(function() {
        if ($(this).is(':checked')) {
            $('.pospRetUrl_fields').show();
            $('.employeeRetUrl_fields').show();
            $('.b2cRetUrl_fields').show();
            $('.b2cDashRetUrl_fields').show();
        } else {
            $('.pospRetUrl_fields').hide();
            $('.employeeRetUrl_fields').hide();
            $('.b2cRetUrl_fields').hide();
            $('.b2cDashRetUrl_fields').hide();
        }
    });

    // To get the initial value on page load
    if ($('#redirection_url_status').is(':checked')) {
        $('.pospRetUrl_fields').show();
        $('.employeeRetUrl_fields').show();
        $('.b2cRetUrl_fields').show();
        $('.b2cDashRetUrl_fields').show();
    } else {
        $('.pospRetUrl_fields').hide();
        $('.employeeRetUrl_fields').hide();
        $('.b2cRetUrl_fields').hide();
        $('.b2cDashRetUrl_fields').hide();
    }
});
    </script>
@endpush

