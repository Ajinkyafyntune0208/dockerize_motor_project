<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password - {{ config('app.name') }}</title>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/fontawesome-free/css/all.min.css')}}">
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/icheck-bootstrap/icheck-bootstrap.min.css')}}">
  <link rel="stylesheet" href="{{asset('admin-lte/dist/css/adminlte.min.css')}}">
</head>
<body class="hold-transition login-page">
<div class="login-box">
 
  <div class="card">
    <div class="card-header text-center">
      <a href="" class="h7"><b>{{ config('app.name') }}</b></a>
    </div>
    <div class="card-body login-card-body">
      <p class="login-box-msg">Reset Your Password</p>

      @if (session('status'))
        <div class="alert alert-success">
          {{ session('status') }}
        </div>
      @endif

      @if (session('error'))
      <div class="alert alert-danger">
        {{ session('error') }}
      </div>
    @endif

      <form action="{{ route('password.update') }}" method="post">
        @csrf
        <input type="hidden" name="linkToken" value="{{request('token')}}">


        <div class="input-group mb-3">
          <input type="email" readonly name="email" class="form-control @error('email') is-invalid @enderror" value="{{ $email ?? old('email') }}" placeholder="Email" required autofocus>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-envelope"></span>
            </div>
          </div>
          @error('email')
            <span class="invalid-feedback" role="alert">
              <strong>{{ $message }}</strong>
            </span>
          @enderror
        </div>

        <div class="input-group mb-3">
          <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="New Password" required>
          <div class="input-group-append">
            <div class="input-group-text" onclick="togglePassword('password', 'passwordIcon')">
              <span id="passwordIcon" class="fas fa-lock"></span>
            </div>
          </div>
          @error('password')
            <span class="invalid-feedback" role="alert">
              <strong>{{ $message }}</strong>
            </span>
          @enderror
        </div>

        <div class="input-group mb-3">
          <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" placeholder="Confirm Password" required>
          <div class="input-group-append">
            <div class="input-group-text" onclick="togglePassword('password_confirmation', 'confirmPasswordIcon')">
              <span id="confirmPasswordIcon" class="fas fa-lock"></span>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
          </div>
        </div>
      </form>

      <p class="mt-3 mb-1" style="text-align: center;">
        <a href="{{ route('admin.login') }}">Back to Login</a>
      </p>
    </div>
  </div>
</div>
<script src="{{asset('admin-lte/plugins/jquery/jquery.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
<script src="{{asset('admin-lte/dist/js/adminlte.min.js')}}"></script>
<script>
  function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-lock');
      icon.classList.add('fa-lock-open');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-lock-open');
      icon.classList.add('fa-lock');
    }
  }
</script>
</body>
</html>
