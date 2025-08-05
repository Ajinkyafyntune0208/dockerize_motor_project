<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    {{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css"> --}}

    <style>
@import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap');
</style>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            font-family: Roboto;
            line-height: 1.15;
            -webkit-text-size-adjust: 100%;
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
        }

        @page {
            margin: 0px;
            padding: 0px;
        }

        a {
            color: #007bff;
            text-decoration: none;
            background-color: transparent;
        }

        a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        th {
            text-align: inherit;
            text-align: -webkit-match-parent;
        }

        .border {
            border: 1px solid #dee2e6 !important;
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        @media (prefers-reduced-motion: reduce) {
            .btn {
                transition: none;
            }
        }

        .btn:hover {
            color: #212529;
            text-decoration: none;
        }

        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            color: #fff;
            background-color: #0069d9;
            border-color: #0062cc;
        }

        .btn-primary:focus,
        .btn-primary.focus {
            color: #fff;
            background-color: #0069d9;
            border-color: #0062cc;
            box-shadow: 0 0 0 0.2rem rgba(38, 143, 255, 0.5);
        }

        body {
            margin: 0;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            text-align: left;
            background-color: #fff;
            margin: 0px;
            font-size: 10px;
            font-family: Roboto;
        }

        hr {
            box-sizing: content-box;
            height: 0;
            overflow: visible;
            /* margin-top: 1rem; */
            margin-bottom: 1rem;
            border: 0;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            margin-top: -1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.5);
        }

        table {
            border-collapse: collapse;
        }


        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
        }

        .table th,
        .table td {
            padding: 1px 10px !important;
            /* padding: 0.75rem; */
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }

        h3 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-weight: 500;
            line-height: 1.2;
            font-size: 1.75rem;
            orphans: 3;
            widows: 3;
            page-break-after: avoid;
        }

        .table-bordered {
            border: 1px solid #dee2e6;
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }

        .table-borderless th,
        .table-borderless td,
        .table-borderless thead th,
        .table-borderless tbody+tbody {
            border: 0;
        }

        .mb-4,
        .my-4 {
            margin-bottom: 1.5rem !important;
        }

        .mb-1,
        .my-1 {
            margin-bottom: 0.25rem !important;
        }

        .mt-4,
        .my-4 {
            margin-top: 1.5rem !important;
        }

        .border-top-0 {
            border-top: 0 !important;
        }
    </style>
</head>

<body>
    <p class="text-center mt-4" style="padding-bottom: .75rem;">
        Proposal
    </p>
    <hr>
    <table class="table table-bordered" style="width: 80%; margin: auto; margin-bottom: 30px;">
        @if (is_array($proposal))
            @foreach ($proposal as $key_data => $data)
                @if (isset($proposal[$key_data]) && !empty($proposal[$key_data]))
                    <tr>
                        <th colspan="2">
                            <h4 style="font-weight: bold; color: #0062cc;">* {{ Str::title($key_data . ' Details') }}
                            </h4>
                        </th>
                    </tr>
                    @foreach ($proposal[$key_data] as $key => $value)
                        @if (is_string($key) && is_string($value))
                            <tr>
                                <th>{{ Str::title(implode(' ', explode('_', Str::snake($key)))) }}</th>
                                <td>{{ $value }}</td>
                            </tr>
                        @endif
                    @endforeach
                @endif
            @endforeach
        @endif
    </table>
</body>

</html>