<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Verify TOTP - {{ config('app.name') }} </title>
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
                            <h6 class="fw-light">Enter email id to receive mail.</h6>
                            <form action="" class="pt-3" method="POST">@csrf
                                <div class="form-group">
                                    <input type="email" name="requested_email" class="form-control form-control-lg" id="requested_email" autofocus>
                                    @error('requested_email')
                                        <span class="invalid-feedback d-inline">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn"
                                        href="#">Send Mail</button>
                                
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"
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

        </script>
</body>

</html>
