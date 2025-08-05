<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ ( $titlePage ?? '' ) . ' | '. config('app.name') }}</title>
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/fontawesome-free/css/all.min.css')}}">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/overlayScrollbars/css/OverlayScrollbars.min.css')}}">
  <!-- DataTables -->
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css')}}">
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css')}}">
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css')}}">
  <!-- Theme style -->
  <link rel="stylesheet" href="{{asset('admin-lte/dist/css/adminlte.min.css')}}">
   <!-- Datepicker -->
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/bootstrap-datepicker/bootstrap-datepicker.min.css')}}">
  <!-- selectpicker -->
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/bootstrap-selectpicker/selectpicker.min.css')}}">
    <!-- loader mmv-sync -->
  <link rel="stylesheet" href="{{asset('admin-lte/plugins/loader/loader.css')}}">

  <link rel="stylesheet" type="text/css" href="{{ asset('css/appAdminlte.css') }}">

  <style>
    #logout-img{
      height: 20px;
      color: rgba(0,0,0,.5);
      margin-top: 2px;
      margin-left: -5px;
      vertical-align: top;
    }
    
    .required::after {
    content: ' *';
    color: red;
    }
    .large-text {
      font-size: large;
    }
    
  .dropdown-divider {
      margin: .3rem 0;
  }
  </style>
  @stack('styles')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<form action="{{ route('admin.logout') }}" method="post" id="logout">@csrf</form>
<!-- Site wrapper -->
<div class="wrapper">
  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

      <!-- Messages Dropdown Menu -->
      <li class="nav-item dropdown">
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <a href="#" class="dropdown-item">
            <!-- Message Start -->
            <div class="media">
              <img src="{{asset('admin-lte/dist/img/user1-128x128.jpg')}}" alt="User Avatar" class="img-size-50 mr-3 img-circle">
              <div class="media-body">
                <h3 class="dropdown-item-title">
                  Brad Diesel
                  <span class="float-right text-sm text-danger"><i class="fas fa-star"></i></span>
                </h3>
                <p class="text-sm">Call me whenever you can...</p>
                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
              </div>
            </div>
            <!-- Message End -->
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <!-- Message Start -->
            <div class="media">
              <img src="{{asset('admin-lte/dist/img/user8-128x128.jpg')}}" alt="User Avatar" class="img-size-50 img-circle mr-3">
              <div class="media-body">
                <h3 class="dropdown-item-title">
                  John Pierce
                  <span class="float-right text-sm text-muted"><i class="fas fa-star"></i></span>
                </h3>
                <p class="text-sm">I got your message bro</p>
                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
              </div>
            </div>
            <!-- Message End -->
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <!-- Message Start -->
            <div class="media">
              <img src="{{asset('admin-lte/dist/img/user3-128x128.jpg')}}" alt="User Avatar" class="img-size-50 img-circle mr-3">
              <div class="media-body">
                <h3 class="dropdown-item-title">
                  Nora Silvester
                  <span class="float-right text-sm text-warning"><i class="fas fa-star"></i></span>
                </h3>
                <p class="text-sm">The subject goes here</p>
                <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
              </div>
            </div>
            <!-- Message End -->
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>
        </div>
      </li>
      <!-- Notifications Dropdown Menu -->
      @php
        $totalNotification = App\Models\Notification::where('is_read','N')->where('to_user', auth()->user()->id)->count();
        $notifications = App\Models\Notification::join('user','user.id', '=', 'notifications.from_user')->where('is_read', 'N')->where('to_user', auth()->user()->id)->select('notifications.id','notifications.content','notifications.from_user','user.name')->get();
        $name = ucfirst(auth()->user()->name); 
        $role = auth()->user()->getRoleNames()->first();
      @endphp 
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
            @if($totalNotification > 0)
              <span class="badge badge-warning navbar-badge">{{ $totalNotification }}</span>
            @endif
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <span class="dropdown-item dropdown-header">{{ $totalNotification }} Notifications</span>
          @foreach($notifications as $data)
          <div class="dropdown-divider"></div>
            <a href="{{ route('admin.authorization_request') }}" class="dropdown-item notification-item" data-id="{{ $data->id }}">
              <i class="fas fa-envelope mr-2"></i> {{ $data->content ." from ".$data->name }}
              {{-- <span class="float-right text-muted text-sm">3 mins</span> --}}
            </a>
          @endforeach
        {{-- <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item dropdown-footer" id="seeAllNotifications">See All Notifications</a> --}}
      </li>
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
      {{-- <li class="nav-item">
        <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
          <i class="fas fa-th-large"></i>
        </a>
      </li>
    --}}
      <!-- User Profile Dropdown Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="fas fa-user"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <span class="dropdown-item dropdown-header large-text">{{ $name ." [". $role."]"}}</span>
          <a class="nav-link" href="{{ route('admin.update-profile', auth()->user()->id) }}" role="button">
            <i class="fas fa-user-edit"></i> Update profile
          </a>   
          @if(auth()->user()->otp_type == 'totp')
            <div class="dropdown-divider"></div>
            <a class="nav-link" href="#" onclick="confirmAction('{{ auth()->user()->email }}')" role="button">
              <i class="fa fa-qrcode"></i> &nbsp;Reset 2FA
            </a>
          @endif  
          <div class="dropdown-divider"></div>
            <a class="nav-link" href="#" role="button">
              <i class="fas fa-key"></i> Reset&nbsp;Password
            </a>
          <div class="dropdown-divider"></div>
          <a class="nav-link" href="#" role="button" onclick="document.getElementById('logout').submit();">
            <i class="fas fa-sign-out-alt"></i>&nbsp;&nbsp; Logout
          </a>
        </div>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  @include('admin_lte.layout.sidebar')

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-1 pt-2">
          <div class="col-sm-6">
            <h3 class="col-12">{{ ( $titlePage  ?? '' ) }}</h3>
          </div>
          <div class="col-sm-6">
          @if(isset($breadcrumbs))
            <ol class="breadcrumb float-sm-right mb-2">
            <li class="breadcrumb-item"><a href="#" id="parent-p">Home</a></li>
                    @foreach ($breadcrumbs as $breadcrumb)
                    
                        @if ($loop->last)
                            <li class="breadcrumb-item active" id="child-p">{{ $breadcrumb->menu_name }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $breadcrumb->menu_url }}">{{ $breadcrumb->menu_name }}</a></li>
                        @endif
                    @endforeach
                </ol>
              @endif 

          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">

      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            @if (session('status'))
            <div class="card-body">
              <div class="alert alert-{{ session('class') }} alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <!-- <h5><i class="icon fas fa-ban"></i> Alert!</h5> -->
                 {{ session('status') }}
              </div>
            </div>
            @endif
            <!-- Default box -->
            @yield('content')
            <!-- /.card -->
          </div>
        </div>
      </div>
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <footer class="main-footer">
    <div class="float-right d-none d-sm-block">
    Latest Build Timestamp : 
    @if (file_exists('./../.git/FETCH_HEAD'))
    {{ date('dS F Y h:i:s A', filemtime('./../.git/FETCH_HEAD')) }} ( Branch : {{ trim(str_replace("'","",explode(' ',file('./../.git/FETCH_HEAD')[0])[1])) }} )
    @endif
    </div>
    Copyright &copy; {{ date('Y') }} <a href="#">{{ config('app.name') }}</a> All rights reserved.
  </footer>

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-light">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="{{asset('admin-lte/plugins/jquery/jquery.min.js')}}"></script>
<!-- overlayScrollbars -->
<script src="{{asset('admin-lte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js')}}"></script>

<!-- DataTables  & Plugins -->
<script src="{{asset('admin-lte/plugins/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/datatables-responsive/js/dataTables.responsive.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/datatables-buttons/js/dataTables.buttons.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/jszip/jszip.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/pdfmake/pdfmake.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/pdfmake/vfs_fonts.js')}}"></script>
<script src="{{asset('admin-lte/plugins/datatables-buttons/js/buttons.html5.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/datatables-buttons/js/buttons.print.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/datatables-buttons/js/buttons.colVis.min.js')}}"></script>
<script src="{{asset('admin-lte/plugins/bootstrap/js/bootstrap5.js')}}"></script>
<!-- datepicker-->
<script src="{{ asset('admin-lte/plugins/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
<!-- selectpicker-->
<script src="{{ asset('admin-lte/plugins/bootstrap-selectpicker/selectpicker.min.js') }}"></script>
<!-- AdminLTE App -->
<script src="{{asset('admin-lte/dist/js/adminlte.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
<script>
  function getElement(element)
  {
    if($.inArray(element, ["Master Product", "MMV Data", "Manufacturer", "Previous Insurer", "Preferred RTO", "Policy Wording", "USP", "Kafka Logs", "Kafka Sync", "Third Party Payment Logs", "Journey Data", "Push Api Data", "IC Master Download"]) !== -1){
      return 350;
    }else if($.inArray(element, ["Broker", "Financier Agreement", "Nominee Relationship", "Gender Mapping", "Payment Response", "RTO Master", "Get Trace ID", "Third Party Api Responses", "Dashboard Mongo Logs", "Onepay Transaction Log", "Data Push Logs"]) !== -1){
      return 600;
    }else if($.inArray(element, ["IC Error Handler", "Encryption/Decryption", "Master Occupation", "Master Occupation Name", "Third Party Settings", "Cashless Garage", "Master Configurator", "Template", "Discount Configuration", "Vahan Service Logs", "User Activity Logs"]) !== -1){
      return 900;
    }else{
      return 0;
    } 
  }

  $(document).ready(function()
  {
    $('.notification-item').on('click', function(e)
    {
      e.preventDefault();
      var notificationId = $(this).data('id');
      var url = '{{ route("admin.markAsRead") }}';
      $.ajax({
        url: url,
        type: 'POST',
        data: {
          _token: '{{ csrf_token() }}',
          id: notificationId
        },
        success: function(response)
        {
          window.location.href = '{{ route("admin.authorization_request") }}';
        },
      });
    });
  });

  setTimeout(function() { $('.alert').fadeOut(); }, 10000);

  function confirmAction(email) {
        var result = confirm("Are you sure you want to proceed?");
        if (result) {
            $.ajax({
                url: '/admin/request_email',
                data: {
                    _token: '{{ csrf_token() }}',
                },
                success: function(response) {
                    alert("Action completed successfully!");
                },
                error: function(xhr, status, error) {
                    alert("An error occurred: " + xhr.responseText);
                    console.error(xhr, status, error);
                }
            });
        } else {
            alert("User canceled!");
        }
  }
</script>
@yield('scripts')
@stack('scripts_lte')
</body>
</html>