<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>{{ config('app.name') }} | Sign in</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="{{ asset('admin1/vendors/feather/feather.css') }}">
  <link rel="stylesheet" href="{{ asset('admin1/vendors/mdi/css/materialdesignicons.min.css') }}">
  <link rel="stylesheet" href="{{ asset('admin1/vendors/ti-icons/css/themify-icons.css') }}">
  <link rel="stylesheet" href="{{ asset('admin1/vendors/typicons/typicons.css') }}">
  <link rel="stylesheet" href="{{ asset('admin1/vendors/simple-line-icons/css/simple-line-icons.css') }}">
  <link rel="stylesheet" href="{{ asset('admin1/vendors/css/vendor.bundle.base.css') }}">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <!-- endinject -->
  <!-- Plugin css for this page -->
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="{{ asset('admin1/css/vertical-layout-light/style.css') }}">
  <!-- endinject -->
  <link rel="shortcut icon" href="{{ asset('admin1/images/favicon.png') }}" />
</head>

<body>
  <div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="content-wrapper d-flex align-items-center auth px-0">
        <div class="row w-100 mx-0">
          <div class="col-lg-4 mx-auto">
            <div class="auth-form-light text-left py-5 px-4 px-sm-5">
              <!-- <div class="brand-logo">
                <img src="{{ asset('admin1/images/logo.svg') }}" alt="logo">
              </div> -->
              <h4>{{ config('app.name') }}</h4>
              <br/>
              <h6 class="fw-light">Sign in to continue.</h6>
              <hr/>
              <form action="" class="pt-3" method="POST">@csrf
                <div class="form-group">
                  <input type="email" name="email" class="form-control form-control-lg" id="exampleInputEmail1" placeholder="Email" value="{{ old('email') }}" required> 
                  @error('email') <span class="invalid-feedback d-inline">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                  <input type="password" name="password" class="form-control form-control-lg" id="exampleInputPassword1" placeholder="Password" required>
                  <i class="fa fa-eye-slash" id="togglePassword" style="display: inline;   height: 82%; position:relative; left: calc(100% - 30px); top:-36px;"></i>
                  @error('password') <span class="invalid-feedback d-inline">{{ $message }}</span>@enderror
                </div>
                
                <div class="my-2 d-flex justify-content-between align-items-center">
                  <div class="form-check">
                    <label class="form-check-label text-muted">
                      <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} class="form-check-input">
                      Keep me signed in
                    </label>
                  </div>
                </div>
                <div class="mt-3">
                  <button class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn" href="#">SIGN IN</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <!-- content-wrapper ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->
  <!-- plugins:js -->
  <script src="{{ asset('admin1/vendors/js/vendor.bundle.base.js') }}"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="{{ asset('admin1/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="{{ asset('admin1/js/off-canvas.js') }}"></script>
  <script src="{{ asset('admin1/js/hoverable-collapse.js') }}"></script>
  <script src="{{ asset('admin1/js/template.js') }}"></script>
  <script src="{{ asset('admin1/js/settings.js') }}"></script>
  <script src="{{ asset('admin1/js/todolist.js') }}"></script>
  <!-- endinject -->
</body>
<script>
    const togglePassword = document.querySelector("#togglePassword");
    const password = document.querySelector("#exampleInputPassword1");

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

</html>
