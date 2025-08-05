<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Verify OTP - {{ config('app.name') }} </title>
    <link rel="stylesheet" href="{{ asset('admin1/css/vertical-layout-light/style.css') }}">
</head>

<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0">
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 mx-auto">
                        <div class="auth-form-light text-left py-5 px-4 px-sm-5">
                            <div class="brand-logo">
                                {{ config('app.name') }}
                            </div>
                            <h4>Two factor Authentication</h4>
                            <h6 class="fw-light">Enter OTP sent to your email.</h6>
                            <form action="" class="pt-3" method="POST">@csrf
                                <div class="form-group">
                                    <input type="text" maxlength="6" name="otp" class="form-control form-control-lg" id="otp" autofocus oninput="validateNumberInput(this)">
                                    @error('otp')
                                        <span class="invalid-feedback d-inline">{{ $message }}</span>
                                    @enderror
                                    <span id="timer"></span>
                                    <a class="text-primary" hidden id="resendOtpBtn" href="#" onclick="resendOtp()">Resend OTP</a>
                                    <input type="text" hidden value="false" name="resend_otp" id="resend_otp">
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn"
                                        href="#">Verify</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="{{ asset('admin1/js/jquery-3.7.1.min.js') }}"
            integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
        <script>
            $(document).ready(function() {
                $("form")[0].reset()
            });
            $("#otp").css('letter-spacing', '20px');

            function validateNumberInput(input) {
                input.value = input.value.replace(/[^0-9]/g, '');
            }
            var timeLeft = 60;
            var elem = document.getElementById('timer');
            var timerId = setInterval(countdown, 1000);

            function countdown() {
                if (timeLeft == -1) {
                    clearTimeout(timerId);
                    elem.setAttribute('hidden', true)
                    $('#resendOtpBtn').attr('hidden', false);
                } else {
                    elem.innerHTML = timeLeft + ' seconds remaining';
                    timeLeft--;
                }
            }

            function resendOtp() {
                console.log("{{ session('user_email') }}");
                $('#resend_otp').val("true")
                $('#otp').val('')
                $("form").submit();
            }
        </script>
</body>

</html>
