<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ ( $titlePage ?? '' ) . ' - '. config('app.name') }}</title>
    <!-- Google Fonts -->
    <!-- <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet" /> -->
    <!-- plugins:css -->
    <link rel="stylesheet" href="{{ asset('admin1/vendors/feather/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('admin1/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin1/vendors/ti-icons/css/themify-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('admin1/vendors/typicons/typicons.css') }}">
    <link rel="stylesheet" href="{{ asset('admin1/vendors/simple-line-icons/css/simple-line-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('admin1/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('admin1/vendors/font-awesome/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta2/dist/css/bootstrap-select.min.css">
    <!-- endinject -->
    <!-- Plugin css for this page -->
    {{--<link rel="stylesheet" href="{{ asset('admin1/vendors/datatables.net-bs4/dataTables.bootstrap4.css') }}">--}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="{{ asset('admin1/js/select.dataTables.min.css') }}">
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="{{ asset('admin1/css/vertical-layout-light/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <!-- endinject -->
    <link rel="shortcut icon" href="images/favicon.png" />

    {{-- Loader css --}}
    <link rel="stylesheet" href="{{asset('css/loader.css')}}">
    <style>
        @media only screen and (min-width: 992px){
            .main-panel {
                position: static;
                top: 0;
                height: 100vh;
                overflow-y: auto;
            }
        }
    </style>
    @stack('styles')
</head>

<body>
    <form action="{{ route('admin.logout') }}" method="post" id="logout">@csrf</form>
    <div class="container-scroller">
    @if(($pageOptions['excludeSideBar'] ?? false) !== true)
        @include('layout.nav.auth')
    @endif
        <!-- partial -->
        <div class="container-fluid page-body-wrapper">
        @if(($pageOptions['excludeNavbar'] ?? false) !== true)
            @include('layout.sidebar')
        @endif
            <!-- partial -->
            <div class="main-panel">
                @yield('content')

                <!-- Loader -->
                <div class="loader-layer d-none">

                </div>
                <div class="loader d-none">
                    <div class="loader-text">
                        Loading ...
                    </div>
                    <div class="circle">
                    </div>
                </div>

            @if(($pageOptions['excludeFooter'] ?? false) !== true)
                @include('layout.footer.auth')
            @endif
            </div>
            <!-- main-panel ends -->
        </div>
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->

    <button type="button" class="btn-sm btn text-center btn-primary btn-floating" id="btn-back-to-top">
        <i class="fa fa-arrow-up text-center"></i>
    </button>

    <!-- plugins:js -->
    <script src="{{ asset('admin1/vendors/js/vendor.bundle.base.js') }}"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <script src="{{ asset('admin1/vendors/chart.js/Chart.min.js') }}"></script>
    <script src="{{ asset('admin1/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('admin1/vendors/progressbar.js/progressbar.min.js') }}"></script>

    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="{{ asset('admin1/js/off-canvas.js') }}"></script>
    <script src="{{ asset('admin1/js/hoverable-collapse.js') }}"></script>
    <script src="{{ asset('admin1/js/template.js') }}"></script>
    <script src="{{ asset('admin1/js/settings.js') }}"></script>
    <script src="{{ asset('admin1/js/todolist.js') }}"></script>
    <script src="{{ asset('admin1/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('admin1/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('admin1/js/bootstrap-select.min.js') }}"></script>
    <!-- endinject -->
    <!-- Custom js for this page-->
    <script src="{{ asset('admin1/js/dashboard.js') }}"></script>
    <script src="{{ asset('admin1/js/Chart.roundedBarCharts.js') }}"></script>
    <!-- End custom js for this page-->
    <script>
        $(document).ready(function() {
            $.fn.selectpicker.Constructor.BootstrapVersion = '5';

                var myDate = new Date();
                var hrs = myDate.getHours();
                var greet;
                if (hrs < 5)
                greet = 'Good Night';
                else if (hrs >= 5 && hrs <= 12)
                greet = 'Good Morning';
                else if (hrs >= 12 && hrs <= 17)
                greet = 'Good Afternoon';
                else if (hrs >= 17 && hrs <= 24)
                greet = 'Good Evening';
                $('#welcome-text').text(greet + ', ');

            var searchInput = $('#searchInput');
            var navItems = $('.nav-item');

            searchInput.on('input', function () {

                var searchValue = $(this).val().toLowerCase();

                navItems.each(function () {
                    var navItemText = $(this).text().toLowerCase();
                    var shouldShow = navItemText.includes(searchValue);
                    $(this).toggle(shouldShow);
                    if(shouldShow){

                        $('.nav-link').removeClass('collapsed');
                        $('.nav-link').attr('aria-expanded', true);
                        $('.collapse').addClass('show');
                    }
                });
            });
            searchInput.on('input', function () {

                var searchValue = $(this).val().toLowerCase();

                if(searchValue == ""){
                    $('.nav-link').addClass('collapsed');
                    $('.nav-link').attr('aria-expanded', false);
                    $('.collapse').removeClass('show');
                }

            });
        });

    </script>
    @stack('scripts')
</body>

</html>
