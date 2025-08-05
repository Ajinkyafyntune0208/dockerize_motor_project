<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - {{ config('app.name') }} </title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/fontawesome-free/css/all.min.css')}}">
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/icheck-bootstrap/icheck-bootstrap.min.css')}}">
  <!-- Theme style -->
  <link rel="stylesheet" href="{{asset('admin-lte/dist/css/adminlte.min.css')}}">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    #visibility{
      cursor: pointer;
    }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box" style="width: 550px; height: 300px;">
  <!-- /.login-logo -->
  <div class="card card-outline card-primary">
    <div class="card-header text-center" >
      <a href="" class="h7" ><b>{{ config('app.name') }}</b></a>
    </div>
    <div class="card-body">
      <!-- <p class="login-box-msg">Hello! let's get started</p> -->

      <form action="" method="post">@csrf
        <div class="input-group mb-3">
          <input type="email" name="email" class="form-control" placeholder="Email" value="{{ old('email') }}" required>
      <div class="input-group-append">
        <div class="input-group-text">
          <span class="fas fa-envelope"></span>
        </div>
      </div>
  @error('email')
    <div class="w-100">
      <span class="invalid-feedback d-inline">{{ $message }}</span>
    </div>
  @enderror
</div>
        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
          @error('password') <span class="invalid-feedback d-inline">{{ $message }}</span>@enderror
          <div class="input-group-append" id="visibility">
            <div class="input-group-text">
              <span class="fas fa-lock"></span>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-8">
            <div class="icheck-primary">
              <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} id="remember">
              <label for="remember">
              Keep me signed in
              </label>
            </div>
          </div>
          <!-- /.col -->
          <div class="col-4">
          @if (  $specificCase != "" )
          <input type="hidden" name="f9a0b94f094c8f89c4cdb5b6e8b67a48" value="{{$specificCase}}" readonly />       
          @endif  
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
          </div>
          <!-- /.col -->
        </div>
      </form>
      <a href="#" data-bs-toggle="modal" data-bs-target="#exampleModal" style="display: block; text-align: center;">Forgot Password</a>
      <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-primary">
              <h5 class="modal-title" id="exampleModalLabel">Forgot Password</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p>Please enter your email address to reset your password.</p>
              <input type="email" class="form-control" id="email" placeholder="Email">
              <div class="invalid-feedback" id="error-message"></div>
            </div>
            <div class="modal-footer justify-content-between">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <div class="btn-group">
              <button type="button" class="btn btn-primary dropdown-toggle" id="submit-btn" data-bs-toggle="dropdown" aria-expanded="false" >
                Send Reset Link
              </button>
              <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="#" id="send-email">Send Email</a></li> 
              <li><a class="dropdown-item" href="#" id="verify-2FA">Verify Using 2FA</a></li>
              </ul>
            </div>
          </div>

          </div>
        </div>
</div>

      <!-- <div class="social-auth-links text-center mt-2 mb-3">
        <a href="#" class="btn btn-block btn-primary">
          <i class="fab fa-facebook mr-2"></i> Sign in using Facebook
        </a>
        <a href="#" class="btn btn-block btn-danger">
          <i class="fab fa-google-plus mr-2"></i> Sign in using Google+
        </a>
      </div> -->
      <!-- /.social-auth-links -->

      <!-- <p class="mb-1">
        <a href="forgot-password.html">I forgot my password</a>
      </p> -->
      <!-- <p class="mb-0">
        <a href="register.html" class="text-center">Register a new membership</a>
      </p> -->
    </div>
    <!-- /.card-body -->
  </div>
  <!-- /.card -->
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="{{asset('admin-lte/plugins/jquery/jquery.min.js')}}"></script>
<!-- Bootstrap 4 -->
<script src="{{asset('admin-lte/plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<!-- AdminLTE App -->
<script src="{{asset('admin-lte/dist/js/adminlte.min.js')}}"></script>
<script>
  $(document).ready(function(){
    $('#visibility').click(function(){
      const span = $(this).children('div').children('span');
      if($('#password').attr('type')=="password"){
        $('#password').attr('type', 'text');
        span.removeClass('fa-lock');
        span.addClass('fa-lock-open');
      }else{
        $('#password').attr('type', 'password');
        span.removeClass('fa-lock-open');
        span.addClass('fa-lock');
      }
    })
  });
</script>
<script>
  $(document).ready(function() {
    $('#send-email').on('click', function() {
      var email = $('#email').val();
      if (email === '') {
        $('#email').addClass('is-invalid');
        $('#error-message').text('Please enter your email address.');
        return;
      } else {
        $('#email').removeClass('is-invalid');
        $('#error-message').text('');
      }
      $('#submit-btn').attr('disabled', true);
      $.ajax({
        url: "{{route('admin.send_email')}}", 
        method: 'POST',
        data: {
          email: email,
          _token: '{{ csrf_token() }}'
        },
        success: function(response) {
          alert('Password reset link sent to your email.');
          $('#exampleModal').modal('hide');
        },
        error: function(xhr) {
          var errorMessage = xhr.responseJSON.message || 'An error occurred.';
          $('#error-message').text(errorMessage).show();
          $('#email').addClass('is-invalid');
        },
        complete: function() {
          $('#submit-btn').attr('disabled', false);
        }
      });
    });
    $('#verify-2FA').on('click', function(e) {
    e.preventDefault();
      var email = $('#email').val();

      if (email === '') {
        $('#email').addClass('is-invalid');
        $('#error-message').text('Please enter your email address.');
        return;
      } else {
        $('#email').removeClass('is-invalid');
        $('#error-message').text('');
      }

    $.ajax({
        url: "{{ route('check.otp.type') }}",
        method: 'POST',
        data: {
            email: email,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.otp_type === 'totp') {
                if (confirm('Are you sure you want to proceed with TOTP verification?')) {

                  window.location.href = "verify_totp_forget_password?email=" + encodeURIComponent(email);
                }
            } else {
                alert('TOTP verification is not enabled for this account.');
            }
        },
        error: function(xhr) {
            $('#error-message').text(xhr.responseJSON.error || 'An error occurred.').show();
            $('#email').addClass('is-invalid');
        }
    });
    }); 
  });
</script>
</body>
</html>
