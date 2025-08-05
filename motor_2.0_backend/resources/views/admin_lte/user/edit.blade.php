@extends('admin_lte.layout.app', ['activePage' => 'admin user', 'titlePage' => __('Admin User')])
@section('content')
    <!-- general form elements disabled -->
    <a class="btn btn-primary mb-3" href="{{ url()->previous() }}"><i class="fa fa-arrow-left mx-2" aria-hidden="true"></i></a>
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Users Edit</h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <form action="{{ route('admin.user.update', $user) }}" method="POST">@csrf @method('PUT')
                <div class="row">
                    <div class="col-sm-12">
                        <!-- text input -->
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" class="form-control" placeholder="Name" name="name" autocomplete="off"
                                value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" placeholder="Email" name="email" autocomplete="off"
                                placeholder="Email" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        {{--
                            <div class="form-group">
                            <label>Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="password"
                                    placeholder="Password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button"
                                        data-target="password">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            @error('password')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password_confirmation"
                                    id="password_confirmation" placeholder="Confirm Password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button"
                                        data-target="password_confirmation">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            @error('password_confirmation')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>--}}
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" class="form-control select2" style="width: 100%;">
                                <option value="">Nothing Selected</option>
                                @foreach ($roles as $key => $role)
                                    {{-- @if (!(auth()->user()->email === 'motor@fyntune.com') && $role->name === 'webservice')
                                        @continue
                                    @endif --}}
                                    <option
                                        {{ old('role', $user->getRoleNames()->first()) == $role->name ? 'selected' : '' }}
                                        value="{{ $role->name }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <div class="form-group row">
                                <label class="required ml-2 mt-2">User Authorization</label>
                                <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                    <input type="checkbox" name="authorization_status" class="custom-control-input"
                                        id="authorization_status" value="on"
                                        {{ old('authorization_status') == 'on' || $user->authorization_status == 'Y' ? 'checked' : '' }}>
                                    <label class="custom-control-label ml-5 mt-2" for="authorization_status"></label>
                                </div>

                                @error('askOtp')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group" id="authorization_user">
                                <label class="required">Authorization By User</label>
                                <select name="authorization_by_user" class="form-control select2" style="width: 100%;">
                                    <option value="">Nothing Selected</option>
                                    @foreach ($user_name as $user_name)
                                        @php
                                            $user_full = $user_name->name . ' [ ' . $user_name->email . ' ]';
                                        @endphp
                                        <option value="{{ $user_name->id }}"
                                            {{ old('authorization_by_user', $user->authorization_by_user) == $user_name->id ? 'selected' : '' }}>
                                            {{ $user_full }}</option>
                                    @endforeach
                                </select>
                                @error('authorization_by_user')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        {{-- <div class="row">
                            <div class="form-group col-6">
                                <label>Ask OTP?</label>
                                <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                    <input type="checkbox" name="askOtp" class="custom-control-input" id="customSwitch3"
                                        {{ $user->confirm_otp == '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="customSwitch3"></label>
                                </div>
                                @error('askOtp')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group col-6">
                                <label class="ml-4.5">OTP Expires In (minutes)</label>
                                <div
                                    class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success pl-0">
                                    <input type="number" name="otpExpiresIn" id="otpExpiresIn" class="form-control"
                                        placeholder="3" max="60" min="3"
                                        value="{{ old('otpExpiresIn', $user->otp_expires_in) }}" required>
                                    @error('otpExpiresIn')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div> --}}
                        <div class="form-group">
                            <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                                <label class="required ml-2 mt-2">Ask OTP?</label>
                                <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                    <input type="checkbox" style="margin-left:0px" name="askOtp" class="custom-control-input" id="askOtp" name="askOtp" {{ $user->confirm_otp == 1 ? 'checked' : '' }}>
                                    <label class="custom-control-label ml-5" for="askOtp"></label>
                                </div>
                                @error('askOtp')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div id="otptypes">
                            <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                                <label for="askOtp" class="col-sm-2 col-form-label required">Type of OTP</label>
                                <div class="col-sm-10">
                                    <select name="authMethod" class="form-control select2" id="authMethod" >
                                        <option value="None" {{ $user->otp_type == 'None' ? 'selected' : '' }}>Nothing Selected</option>
                                        <option value="email_otp" {{ $user->otp_type == 'email_otp' ? 'selected' : '' }}>Email OTP</option>
                                        <option value="totp" {{ $user->otp_type == 'totp' ? 'selected' : '' }} >2FA Authentication</option>
                                    </select>
                                </div>
                            </div>
                                <div class="form-group row" id="expireField" style="display: none;" style="display: flex; flex-direction: row; align-items: center;">
                                    <label for="otpExpiresIn" class="col-sm-2 col-form-label required">OTP Expires In</label>
                                    <div class="col-sm-10">
                                        <input type="number" name="otpExpiresIn" id="otpExpiresIn" class="form-control" placeholder="{{isset($user->otp_expires_in) ? $user->otp_expires_in : 3}} (minutes)" max="60" min="3" value="{{isset($user->otp_expires_in) ? $user->otp_expires_in : 3}}" >
                                        @error('otpExpiresIn')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection('content')
@section('scripts')
    <script>
        $(document).ready(function() {
            $('#authorization_status').on('change', function() {
                // Get the value of the checkbox
                var isChecked = $(this).prop('checked');
                if (isChecked == true) {
                    $('#authorization_user').show();
                } else {
                    $('#authorization_user').hide();
                }
            });

            // You can also get the value of the checkbox when needed
            var isChecked = $('#authorization_status').prop('checked');
            if (isChecked == true) {
                $('#authorization_user').show();
            } else {
                $('#authorization_user').hide();
            }

            $('.toggle-password').on('click', function() {
                var target = $(this).data('target');
                var input = $('#' + target);
                var icon = $(this).find('i');

                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
        });

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
@endsection
