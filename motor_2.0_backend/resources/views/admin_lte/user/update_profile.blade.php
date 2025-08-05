@extends('admin_lte.layout.app', ['activePage' => 'Update Profile', 'titlePage' => __('Update Profile')])
@section('content')
    <a class="btn btn-primary mb-3" href="{{ url()->previous() }}"><i class="fa fa-arrow-left mx-2" aria-hidden="true"></i></a>
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Profile Edit</h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <form action="{{ route('admin.save-profile', $user) }}" method="POST">@csrf @method('post')
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
                                placeholder="Email" value="{{ old('email', $user->email) }}" readonly>
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" class="form-control select2" style="width: 100%;" disabled>
                                <option value="">Nothing Selected</option>
                                @foreach ($roles as $key => $role)
                                    @if (!(auth()->user()->email === 'motor@fyntune.com') && $role->name === 'webservice')
                                        @continue
                                    @endif
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
                            <label>Current Password</label>
                            <div class="input-group mb-3">
                                <input type="password" name="current_password"
                                    class="form-control @error('current_password') is-invalid @enderror"
                                    placeholder="Current Password">
                                @error('current_password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <div class="input-group mb-3">
                                <input type="password" name="password"
                                    class="form-control @error('password') is-invalid @enderror" placeholder="New Password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Confirmed Password</label>
                            <div class="input-group mb-3">
                                <input type="password" name="password_confirmation" class="form-control"
                                    placeholder="Confirm Password">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-group row">
                                <label class="required ml-2 mt-2">User Authorization</label>
                                <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                    <input type="text" id="name" name="name" class="form-control"
                                    value="{{ isset($user->authorization_by_user) ? $user->authorization_by_user : 'N/A' }}"
                                    disabled>
                                </div>

                                @error('askOtp')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group" id="authorization_user">
                                <label class="required">Authorization By User</label>
                                <select name="authorization_by_user" class="form-control select2" style="width: 100%;" disabled>
                                    <option value="">Nothing Selected</option>
                                    @foreach ($user_name as $user_name)
                                        @php
                                            $user_full = $user_name->name . ' [ ' . $user_name->email . ' ]';
                                        @endphp
                                        <option value="{{ $user_full }}"
                                            {{ old('authorization_by_user', $user->authorization_by_user) == $user_full ? 'selected' : '' }}>
                                            {{ $user_full }}</option>
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
                                    <input type="text" id="otp_type" name="otp_type" class="form-control"
                                    value="{{ isset($user->otp_type) ? $user->otp_type : 'N/A' }}" readonly>
                                </div>
                                @if ($user->otp_type == 'totp')
                                <button type="button" class="btn btn-primary" data-toggle="modal"
                                    data-target="#exampleModalCenter"> veiw</button>&nbsp;<a href="{{ asset('storage/download_files/qrCodePdf/qrcode' . $user->totp_secret . '.pdf') }}"
                                  class="btn btn-primary"  download> <i class="fa fa-download" aria-hidden="true"></i></a>
                            @endif
                                @error('askOtp')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div id="otptypes">
                            <div class="form-group row" style="display: flex; flex-direction: row; align-items: center;">
                                <label for="askOtp" class="col-form-label required">Type of OTP</label>
                                <div class="col-sm-10">
                                    <select name="authMethod" class="form-control select2" id="authMethod" disabled>
                                        <option value="None" {{ $user->otp_type == 'None' ? 'selected' : '' }}>Nothing Selected</option>
                                        <option value="email_otp" {{ $user->otp_type == 'email_otp' ? 'selected' : '' }}>Email OTP</option>
                                        <option value="totp" {{ $user->otp_type == 'totp' ? 'selected' : '' }} >2FA Authentication</option>
                                    </select>
                                </div>
                            </div>
                                <div class="form-group row" id="expireField" style="display: none;" style="display: flex; flex-direction: row; align-items: center;">
                                    <label for="otpExpiresIn" class="col-sm-2 col-form-label required">OTP Expires In</label>
                                    <div class="col-sm-10">
                                        <input type="number" name="otpExpiresIn" id="otpExpiresIn" class="form-control" placeholder="3 (minutes)" max="60" min="3" value="3" required>
                                        @error('otpExpiresIn')<span class="text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>
                                <div class="form-group row" id="totp_secret" style="display: none;"
                                style="display: flex; flex-direction: row; align-items: center;">
                                <label for="totp_secret" class="col-sm-2 col-form-label required">TOTP Secret
                                    Key</label>
                                <div class="col-sm-10">
                                    <input type="text" name="totp_secret" id="totp_secret" class="form-control"
                                        placeholder="Totp Secret key" value={{ $user->totp_secret }}>
                                </div>
                            </div>
                            <div class="form-group row" id="confirm_otp" style="display: none;"
                                style="display: flex; flex-direction: row; align-items: center;">
                                <label for="confirm_otp" class="col-sm-2 col-form-label required">Confirm Otp</label>
                                <div class="col-sm-10">
                                    <input type="text" name="confirm_otp" id="confirm_otp" class="form-control"
                                        placeholder="Confirm OTP" value={{ $user->confirm_otp }}>
                                </div>
                            </div>
                            <div class="form-group row" id="expireField" style="display: none;"
                                style="display: flex; flex-direction: row; align-items: center;">
                                <label for="otpExpiresIn" class="col-sm-2 col-form-label required">OTP Expires
                                    In</label>
                                <div class="col-sm-10">
                                    <input type="number" name="otpExpiresIn" id="otpExpiresIn" class="form-control"
                                        placeholder="3 (minutes)" max="60" min="3" value="3" required>
                                    @error('otpExpiresIn')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
        aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <iframe src="{{ asset('storage/download_files/qrCodePdf/qrcode' . $user->totp_secret . '.pdf') }}"
                        type="application/pdf" width="100%" height="500px">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
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
