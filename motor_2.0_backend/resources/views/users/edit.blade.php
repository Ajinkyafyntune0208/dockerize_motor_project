@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Users List')])
@section('content')
<!-- partial -->
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Users List</h5>
                    @if (session('status'))
                    <div class="alert alert-{{ session('class') }}">
                        {{ session('status') }}
                    </div>
                    @endif
                    @if(session('is_configured'))
                            <div class="alert alert-danger">{{ session('is_configured') }}</div >
                    @endif
                    <form action="{{ route('admin.user.update', $user) }}" method="POST">@csrf @method('PUT')
                        <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                            <label for="name" class="col-sm-2 col-form-label required">Name</label>
                            <div class="col-sm-10">
                                <input type="text" name="name" class="form-control" id="name" placeholder="Name" value="{{ old('name', $user->name ) }}" required>
                                @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                            <label for="email" class="col-sm-2 col-form-label required">Email</label>
                            <div class="col-sm-10">
                                <input type="email" name="email" class="form-control" id="email" autocomplete="off" value="{{ old('email', $user->email ) }}" placeholder="Email" required>
                                @error('email')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                            <label for="password" class="col-sm-2 col-form-label">Password</label>
                            <div class="col-sm-10">
                                <input type="password" onselectstart="return false" onpaste="return false;" onCopy="return false" onCut="return false" onDrag="return false" onDrop="return false" name="password" onkeyup="checkFuntion()" class="form-control" id="password" placeholder="Password">
                                <i class="fa fa-eye-slash" id="togglePassword" style="display: flex; align-items: center; position:relative;  height: 82%; left:calc(100% - 30px); bottom: 25px;"></i>
                                @error('password')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                            <span class="row" style="font-size: x-small;color:red;margin-left:180px;">
                                <div class="col-sm">
                                @if ($policyData['password.upperCaseLetter'] == 'Y')
                                    <p style="font-size: x-small;" id="1">* Uppercase letter is required.</p>
                                @endif
                                @if ($policyData['password.lowerCaseLetter'] == 'Y')
                                    <p style="font-size: x-small;" id="2">* Lowercase letter is required.</P>
                                @endif
                                </div>
                                <div class="col-sm" style="margin-right:100px">
                                @if ($policyData['password.atLeastOneNumber'] == 'Y')
                                    <p style="font-size: x-small;" id="3">* At least one number is required.</p>
                                @endif
                                @if ($policyData['password.atLeastOneSymbol'] == 'Y')
                                    <p style="font-size: x-small; " id="4">* At least one symbol is required.
                                    </p>
                                @endif
                                </div>
                            </span>
                        </div>
                        <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                            <label for="password_confirmation" class="col-sm-2 col-form-label">Confirm Password</label>
                            <div class="col-sm-10">
                                <input type="password" onselectstart="return false" onpaste="return false;" onCopy="return false" onCut="return false" onDrag="return false" onDrop="return false" name="password_confirmation" class="form-control" id="password_confirmation" placeholder="Confirm Password">
                            </div>
                        </div>
                        <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                            <label for="role" class="col-sm-2 col-form-label required">Role</label>
                            <div class="col-sm-10">
                                <select name="role" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" required>
                                    <option value="">Nothing Selected</option>
                                    @foreach($roles as $key => $role)
                                    @if (!(auth()->user()->email==='motor@fyntune.com')  && $role->name==='webservice')
                                        @continue
                                    @endif
                                    <option {{ old('role', $user->getRoleNames()->first()) == $role->name ? 'selected' : '' }} value="{{ $role->name }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('role')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                            <label for="askOtp" class="col-sm-2 col-form-label required">Ask OTP ?</label>
                            <div class="col-sm-10">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" style="margin-left:0px" type="checkbox" role="switch" id="askOtp"  name= "askOtp" {{ $user->confirm_otp == 1 ? 'checked' : '' }}>
                                  </div>
                                @error('askOtp')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div id="otptypes">
                            <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                                <label for="askOtp" class="col-sm-2 col-form-label required">Type of OTP</label>
                                <div class="col-sm-10">
                                    <select name="authMethod" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" id="authMethod" >
                                        <option value="None" {{ $user->otp_type == 'None' ? 'selected' : '' }}>Nothing Selected</option>
                                        <option value="email_otp" {{ $user->otp_type == 'email_otp' ? 'selected' : '' }}>Email OTP</option>
                                        <option value="totp" {{ $user->otp_type == 'totp' ? 'selected' : '' }} >2FA Authentication</option>
                                    </select>
                                </div>
                            </div>
                                <div class="form-group row" id="expireField" style="display: none;" style="display: flex; flex-direction: row; align-items: center;">
                                    <label for="otpExpiresIn" class="col-sm-2 col-form-label required">OTP Expires In</label>
                                    <div class="col-sm-10">
                                        <input type="number" name="otpExpiresIn" id="otpExpiresIn" class="form-control" placeholder="3 (minutes)" max="60" min="3" value="3" >
                                        @error('otpExpiresIn')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                        </div>
                        <button type="submit" class="btn btn-primary me-2">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    $(document).ready(function() {
        const isAdmin = "{{ $user->email === 'motor@fyntune.com' ? 'disabled' : '' }}";
        $('input,select,button').attr(isAdmin,true);
    });
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#password");

    togglePassword.addEventListener("click", function () {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        if(type == "text"){
            togglePassword.classList.remove("fa-eye-slash");
            togglePassword.classList.add("fa-eye");
        }else{
            togglePassword.classList.add("fa-eye-slash");
            togglePassword.classList.remove("fa-eye");  
        }
    });
</script>
<script>
    function checkFuntion(){
        var char = $('#password').val();
         if(char.length == '0'){
            $('#1,#2,#3,#4').css('color','red');
        }

        var hasUpperCaseLetter = /[A-Z]/g ;
        var hasLowerCaseLetter = /[a-z]/g ;
        var hasAtLeastOneNumber =  /[0-9]/g;
        var hasAtLeastOneSymbol = /[@$!%*#?&]/g;
 
        if(char.match(hasUpperCaseLetter)){
            $('#1').css('color','green');
        }
        else if(!char.match(hasUpperCaseLetter)){
            $('#1').css('color','red');
        }
        if(char.match(hasLowerCaseLetter)){
            $('#2').css('color','green');
        }
        else if(!char.match(hasLowerCaseLetter)){
            $('#2').css('color','red');
        }
        if(char.match(hasAtLeastOneNumber)){
            number = true;
            $('#3').css('color','green');
        }
        else if(!char.match(hasAtLeastOneNumber)){
            $('#3').css('color','red');
        }
        if(char.match(hasAtLeastOneSymbol)){
            $('#4').css('color','green');
        }
            else if(!char.match(hasAtLeastOneSymbol)){
            $('#4').css('color','red');
        }    
    }
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const showType = "{{ $user->confirm_otp == 1 ? 'Block' : 'None' }}";
    document.getElementById('otptypes').style.display = showType;
    const askOtpCheckbox = document.getElementById('askOtp');
    const otptypes = document.getElementById('otptypes');
    const authMethodSelect = document.getElementById('authMethod');
    const expireField = document.getElementById('expireField');

    authMethodSelect.addEventListener('change', function() {
        if (authMethodSelect.value === 'email_otp') {
            expireField.style.display = 'block';
            expireField.style.display = 'flex';
            expireField.style = ('flex-direction: row');
            expireField.style = ('align-items: center')
        } else {
            expireField.style.display = 'none';
        }
    });
    askOtpCheckbox.addEventListener('change', function(){
        if (askOtpCheckbox.checked) {
            otptypes.style.display = 'block';
            toggleEmailField();
        } else {
            otptypes.style.display = 'none';
        }
    });
});
</script>
@endpush