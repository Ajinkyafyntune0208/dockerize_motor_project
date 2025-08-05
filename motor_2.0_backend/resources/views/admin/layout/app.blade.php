<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet" />
    <!-- MDB -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/dataTables.bootstrap4.min.css">
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta2/dist/css/bootstrap-select.min.css">
    <link rel="stylesheet" href="{{asset('css/admin.css')}}">


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.52.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.52.2/theme/material-ocean.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.52.2/addon/hint/show-hint.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.6.0/bootstrap-tagsinput.min.css">
    <style type="text/css">
        .bootstrap-tagsinput {
            width: 100%;
        }

        .label-info {
            background-color: #007bff;

        }

        .label {
            display: inline-block;
            padding: .25em .4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .25rem;
            transition: color .15s ease-in-out, background-color .15s ease-in-out,
                border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
        @media only screen and (min-width: 992px){
            .main-panel {
                position: static;
                top: 0;
                height: 100vh;
                overflow-y: auto;
            }
            #sidebar {
                position: static;
                top: 0;
                height: 100vh;
                overflow-y: auto;
            }
        }
    </style>
</head>

<body>
    <!--Main Navigation-->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary" id="sidebar">
            <div class="container">
                <a class="navbar-brand" href="#">{{ config('app.name') }}</a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.logs.index') }}">Logs</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.configuration.index') }}">Configuration</a>
                        </li>
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.email-sms-template.index') }}">Email SMS Template</a>
                        </li> -->
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.policy-wording.index') }}">Policy Wording</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.company.index') }}">Company Logo</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.usp.index') }}">USP</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <!--Main Navigation-->

    <!--Main layout-->
    <main style="margin-top: 58px">
        <div class="">
            @yield('content')
        </div>
    </main>
    <!--Main layout-->
    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script> -->

    <script src="{{asset('js/jquery-3.5.1.min.js')}}"></script>
    <script src="{{asset('js/popper.min.js')}}"></script>
    <script src="{{asset('js/bootstrap.min.js')}}"></script>

    <script src="{{asset('js/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('js/dataTables.bootstrap4.min.js')}}"></script>
    <!-- Latest compiled and minified JavaScript -->
    <script src="{{asset('js/bootstrap-select.min.js')}}"></script>
    <!-- <script src="//cdn.ckeditor.com/4.16.2/full/ckeditor.js"></script> -->

    <script src="{{asset('js/codemirror.min.js')}}"></script>
    <script src="{{asset('js/xml')}}"></script>
    <script src="{{asset('js/javascript')}}"></script>
    <script src="{{asset('js/css')}}"></script>
    <script src="{{asset('js/htmlmixed.js')}}"></script>
    <script src="{{asset('js/matchbrackets.js')}}"></script>
    <script src="{{asset('js/show-hint.js')}}"></script>
    <script src="{{asset('js/javascript-hint.js')}}"></script>
    <script src="{{asset('js/html-hint')}}"></script>
    <script src="{{asset('js/xml-hint.js')}}"></script>
    <script src="{{asset('js/css-hint.js')}}"></script>
    <script src="{{asset('js/sublime.js')}}"></script>
    <script src="{{asset('js/bootstrap-tagsinput.min.js')}}"></script>
    <script>
        $(document).ready(function() {

            $.fn.selectpicker.Constructor.BootstrapVersion = '4';
            $('input[data-role="tagsinput"]').tagsinput({
                confirmKeys: [13, 188]
            });

            $('.bootstrap-tagsinput ').addClass('form-control');
            $('.bootstrap-tagsinput ').on('keypress', function(e) {
                if (e.keyCode == 13) {
                    e.keyCode = 188;
                    e.preventDefault();
                };
            });
        })
    </script>
    @stack('scripts')
</body>

</html>
