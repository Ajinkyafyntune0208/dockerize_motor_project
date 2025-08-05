@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Reset Password')])
@section('content')

    <style>
    @media only screen and (min-width: 992px) {
        .expand {
       width: 650px;
      }
    }
    </style>
    <!-- partial -->
    <div class="container-fluid d-flex justify-content-center vw-100" >
        <div class="row d-flex justify-content-center">
            <div class="col-sm-8 grid-margin stretch-card expand" >
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h5 class="card-title">Reset Password</h5>
                        @if (session('status'))
                            <div id="session" class="alert alert-{{ session('class') }}">
                                {{ session('status') }} 
                            </div>
                        @endif
                        <form action="{{ route('update-reset-password', ['id' => $id]) }}" method="POST">@csrf
                            @method('PUT')
                            
                            <div class="form-group row" onkeyup="checkFuntion()"
                                style="display: flex; flex-direction: row; align-items: center;">
                                <label for="password" class="col-sm-2 col-form-label required">Password</label>
                                <div class="col-sm-10">
                                    @error('password')
                                        <span style="font-size: 12px;" class="text-danger">{{ $message }}</span>
                                    @enderror
                                    <input type="password" onselectstart="return false" onpaste="return false;" onCopy="return false" onCut="return false" onDrag="return false" onDrop="return false" name="password" class="form-control" id="password" placeholder="Password">
                                    <i class="fa fa-eye-slash" id="togglePassword"
                                        style="display: flex; align-items: center; position:relative;  height: 82%; left:calc(100% - 30px); bottom: 25px;"></i>
                                </div>
                                <span class="row text-danger" style="font-size: 11px;margin:-10px 0 -25px 100px;">
                                    <div class="col-sm">
                                    @if ($policyData['password.upperCaseLetter'] == 'Y')
                                        <p style="font-size: 12px;" id="1">* Uppercase letter is required.</p>
                                    @endif
                                    @if ($policyData['password.lowerCaseLetter'] == 'Y')
                                        <p style="font-size: 12px;" id="2">* Lowercase letter is required.</P>
                                    @endif
                                    </div>
                                    <div class="col-sm" style="margin-right:100px">
                                    @if ($policyData['password.atLeastOneNumber'] == 'Y')
                                        <p style="font-size: 12px;" id="3">* At least one number is required.</p>
                                    @endif
                                    @if ($policyData['password.atLeastOneSymbol'] == 'Y')
                                        <p style="font-size: 12px; " id="4">* At least one symbol is required.
                                        </p>
                                    @endif
                                    </div>
                                </span>
                            </div>
                            <div class="form-group row"
                                style="display: flex; flex-direction: row; align-items: center;margin-top:-15px;">
                                <label for="password_confirmation" class="col-sm-2 col-form-label required">Confirm
                                    Password</label>
                                <div class="col-sm-10">
                                    <input type="password" onselectstart="return false" onpaste="return false;" onCopy="return false" onCut="return false" onDrag="return false" onDrop="return false" name="password_confirmation" class="form-control" id="password_confirmation" placeholder="Confirm Password">
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
    $(document).ready(function() {});
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#password");

    togglePassword.addEventListener("click", function() {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        if (type == "text") {
            togglePassword.classList.remove("fa-eye-slash");
            togglePassword.classList.add("fa-eye");
        } else {
            togglePassword.classList.add("fa-eye-slash");
            togglePassword.classList.remove("fa-eye");
        }
    });
</script>

<script>
    function checkFuntion() {
        var char = $('#password').val();
        if (char.length == '0') {
            $('#1,#2,#3,#4').css('color', 'red');
        }

        var hasUpperCaseLetter = /[A-Z]/g;
        var hasLowerCaseLetter = /[a-z]/g;
        var hasAtLeastOneNumber = /[0-9]/g;
        var hasAtLeastOneSymbol = /[@$!%*#?&]/g;

        if (char.match(hasUpperCaseLetter)) {
            $('#1').css('color', 'green');
        } else if (!char.match(hasUpperCaseLetter)) {
            $('#1').css('color', 'red');
        }
        if (char.match(hasLowerCaseLetter)) {
            $('#2').css('color', 'green');
        } else if (!char.match(hasLowerCaseLetter)) {
            $('#2').css('color', 'red');
        }
        if (char.match(hasAtLeastOneNumber)) {
            $('#3').css('color', 'green');
        } else if (!char.match(hasAtLeastOneNumber)) {
            $('#3').css('color', 'red');
        }
        if (char.match(hasAtLeastOneSymbol)) {
            $('#4').css('color', 'green');
        } else if (!char.match(hasAtLeastOneSymbol)) {
            $('#4').css('color', 'red');
        }
    }
</script>

<script>
    setTimeout(hideSession, 8000)
    function hideSession(){
        $('#session').hide();
    }
</script>
@endpush
