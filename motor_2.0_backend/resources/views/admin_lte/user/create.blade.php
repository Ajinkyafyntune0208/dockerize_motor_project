@extends('admin_lte.layout.app', ['activePage' => 'admin user', 'titlePage' => __('Admin User')])
@section('content')
    <!-- general form elements disabled -->
    <a class="btn btn-primary mb-3" href="{{ route('admin.user.index')}}"><i class="fa fa-arrow-left mx-2" aria-hidden="true"></i></a>
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Users Add</h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <form action="{{ route('admin.user.store') }}" method="POST">@csrf
                <div class="row">
                    <div class="col-sm-12">
                        <!-- text input -->
                        <div class="form-group">
                            <label class="required">Name</label>
                            <input type="text" class="form-control" placeholder="Name" name="name" autocomplete="off"
                                value="{{ old('name') }}" required>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="required">Email</label>
                            <input type="email" class="form-control" placeholder="Email" name="email" autocomplete="off"
                                value="{{ old('email') }}" required>
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="password"
                                    placeholder="Password" value="{{-- old('password') --}}">
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
                                    id="password_confirmation" placeholder="Confirm Password" value="{{-- old('password_confirmation') --}}">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary toggle-password" type="button"
                                        data-target="password_confirmation">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>
                            @error('password_confirmation')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="required">Role</label>
                            <select name="role" class="form-control select2" style="width: 100%;">
                                <option value="">Nothing Selected</option>
                                @foreach ($roles as $key => $role)
                                    {{-- @if (!(auth()->user()->email === 'motor@fyntune.com') && $role->name === 'webservice')
                                        @continue
                                    @endif --}}
                                    <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
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
                                    <input type="checkbox" name="authorization_status" class="custom-control-input" id="authorization_status"
                                        value="on" {{ old('authorization_status') == 'on' ? 'checked' : '' }}>
                                    <label class="custom-control-label ml-5 mt-2" for="authorization_status"></label>
                                </div>
                                @error('authorization_status')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group" id="authorization_user" style="{{ old('authorization_status', 'on') == 'on' ? '' : 'display:none' }}">
                                <label class="required">Authorization By User</label>
                                <select name="authorization_by_user" id="authorization_by_user" class="form-control select2" style="width: 100%;" >
                                    <option value="">Nothing Selected</option>
                                    @foreach ($user_name as $user)
                                        <option value="{{ $user->id }}" {{ old('authorization_by_user') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name . ' [ ' . $user->email . ' ]' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('authorization_by_user')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                                <label class="required ml-2 mt-2">Ask OTP?</label>
                                <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                    <input type="checkbox" style="margin-left:0px" name="askOtp" class="custom-control-input" id="askOtp"
                                        {{ old('askOtp', 'off') == 'on' ? 'checked' : '' }}>
                                    <label class="custom-control-label ml-5" for="askOtp"></label>
                                </div>
                                @error('askOtp')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                                </div>

                        <div class="form-group" id="otptypes" style="{{ old('askOtp', 'off') == 'on' ? '' : 'display:none;' }}">
                            <label for="askOtp" class="required">Type of OTP</label>
                            <select name="authMethod" class="form-control select2" id="authMethod">
                                <option value="">Nothing Selected</option>
                                <option value="email_otp" {{ old('authMethod') == 'email_otp' ? 'selected' : '' }}>Email OTP</option>
                                <option value="totp" {{ old('authMethod') == 'totp' ? 'selected' : '' }}>2FA Authentication</option>
                            </select>

                            <div class="form-group mt-2" id="expireField" style="{{ old('authMethod') == 'email_otp' ? 'display:block;' : 'display:none;' }}">
                                <label for="otpExpiresIn" class="required">OTP Expires In</label>
                                <input type="number" name="otpExpiresIn" id="otpExpiresIn" style="width: 100%;" class="form-control" placeholder="3 (minutes)" max="60" min="3" value="{{ old('otpExpiresIn', 3) }}">
                                @error('otpExpiresIn')<span class="text-danger">{{ $message }}</span>@enderror
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
            // You can also get the value of the checkbox when needed
            var isChecked = $('#authorization_status').prop('checked');
            if (isChecked == true) {
                $('#authorization_user').show();
            } else {
                $('#authorization_user').hide();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const askOtpCheckbox = document.getElementById('askOtp');
            const otptypes = document.getElementById('otptypes');
            const authMethodSelect = document.getElementById('authMethod');
            const expireField = document.getElementById('expireField');

            authMethodSelect.addEventListener('change', function() {
                if (authMethodSelect.value === 'email_otp') {
                    expireField.style.display = 'block';
                    expireField.style.display = 'block';
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
        })
    </script>
@endsection
