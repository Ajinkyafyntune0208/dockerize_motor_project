<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    {{-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css"> --}}
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            padding: 0px;
            margin: 0px;
        }

        html {
            font-family: sans-serif;
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

        .text-left {
            text-align: left !important;
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
            font-family: DejaVu Sans, sans-serif;
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

        footer {
            font-size: bold;
            position: absolute;
            left: 0;
            bottom: 25px;
            width: 100%;
            text-align: center;
        }

        .tmibasl-footer {
            font-size: bold;
            position: absolute;
            left: 0;
            bottom: 0px;
            width: 100%;
            text-align: center;
        }

        .tmibasl-footer p {
            margin-bottom: 3px;
        }

        .note {
            font-size: bold;
            position: relative;
            left: 10px;
            bottom: -25px;
            width: 100%;
        }

        .note-sriyah {
            font-size: bold;
            position: absolute;
            left: 10px;
            bottom: 90px;
            width: 100%;
        }

        .buy-btn{
            font-weight: bold;
        }

        </style>

    @if (config('constants.motorConstant.SMS_FOLDER') == 'tmibasl') 

    <style>
    @font-face {
        font-family: 'Roboto';
        src: url('{{ url('fonts/Roboto/Roboto-Regular.ttf') }}') format("truetype");
        font-weight: normal;
        font-style: normal;
        font-variant: normal;
    }

    @font-face {
        font-family: 'Roboto-Bold';
        src: url('{{ url('fonts/Roboto/Roboto-Medium.ttf') }}') format("truetype");
        font-weight: bold;
        font-style: bold;
        font-variant: bold;
    }

    h1, h2, h3, h4, h5, h6, b, strong, th{
        font-family: "Roboto-Bold" !important; 
        font-weight: bold;  
    }

    p, span, td, a {
        font-family: "Roboto" !important; 
        font-weight: normal !important;
    }

    </style>
@endif
</head>

<body>

    @if (config('constants.motorConstant.SMS_FOLDER') === 'sriyah')
        <table class='table'>
            <tr style="margin-bottom: 3%">
                <td width="30%">
                    <img src="{{ $data['site_logo'] ?? '' }}" alt="#"
                        style="width: auto; margin-top: 10px; height: 70px; text-align: center">
                </td>

                <td style='text-align:center' width="30%">
                    <img class="rounded-circle" src="{{ $data['policy_type_logo'] ?? '' }}"
                        style="height: 30px; width: auto; background-color: #fff;">
                    <p style="font-weight:bold;margin-bottom:3px">Premium Breakup Report</p>
                    <p>{{ $data['product_name'] ?? '' }}</p>
                </td>

                <td style='text-align:right' width="30%">
                    <p style="font-weight:bold">www.nammacover.com</p>
                    <p style="font-weight:bold">{{ $data['support_email'] ?? '' }}</p>
                    <p style="font-weight:bold">{{ str_replace(['+', ' '], ['', '-'], $data['toll_free_number']) }}
                    </p>
                </td>
            </tr>

        </table>
    @endif

    <div style="{{ $style ?? '' }}">
        @if (isset($data['btn_link']) && $data['btn_link'] != null)
            @php
                $url = $data['btn_link'];
                $url = explode('enquiry_id=', $url);
                $url = explode('&', end($url));
            @endphp

            <p class="text-right" style="margin-bottom: -25px; margin-right: 15px; font-size: 12px; padding:0px">
                <b>Trace ID:</b>
                @if(config('enquiry_id_encryption') == 'Y')
                    {{ $data['traceId'] ?? '' }}
                @else
                    {{ $url[0] ?? '' }}
                @endif
                <br>
            </p>        
        @endif

        @if (config('constants.motorConstant.SMS_FOLDER') !== 'sriyah')
            <h3 class="my-4 text-center">
                Premium Breakup
            </h3>
            <hr>
        @endif

        <table class="table">

            @if (config('constants.motorConstant.SMS_FOLDER') === 'sriyah')
                <tr>
                    <td class="border-top-0" width="50%">
                        <table class="table-bordered table">
                            <tr>
                                <td class="text-center" width="30%" rowspan="2"><img
                                        src="{{ $data['ic_logo'] ?? '' }}" alt="{{ $data['ic_name'] ?? '' }}"
                                        style="width: auto !important; margin-top: 10px; height: 50px; text-align: center">
                                </td>
                                <td>{{ $data['ic_name'] ?? '' }}</td>
                            </tr>
                            <tr>
                                <td>{{ $data['product_name'] ?? '' }}</td>
                            </tr>
                        </table>
                    </td>
                    <td class="border-top-0" width="50%"></td>
                </tr>
            @else
                <tr>
                    <td class="border-top-0" width="50%">
                        <table class="table-bordered table">
                            <tr>
                                @if (config('constants.motorConstant.SMS_FOLDER') == 'abibl')
                                    <td class="text-center" width="30%" rowspan="2"><img
                                            src="{{ $data['site_logo'] ?? '' }}" alt="#"
                                            style="width: auto; margin-top: 5px; height: 35px; text-align: center">

                                    </td>
                                @else
                                @if (config('constants.motorConstant.SMS_FOLDER') == 'bajaj')
                                        <td class="text-center" width="30%" rowspan="2">
                                            <img src="{{ asset('/broker-logos/bajaj_logo.png') }}" alt="Bajaj Logo"
                                                style="width: auto; margin-top: 5px; height: 35px; text-align: center">
                                        </td>
                                @else
                                    <td class="text-center" width="30%" rowspan="2"><img
                                            src="{{ $data['site_logo'] ?? '' }}" alt="#"
                                            style="width: auto; margin-top: 10px; height: 50px; text-align: center">

                                    </td>
                                @endif
                                @endif
                                <td class="text-center">
                                    <a href="{{ $data['toll_free_number_link'] ?? '' }}"
                                        target="_blank">{{ $data['toll_free_number'] ?? '' }}</a>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-center">
                                    <a href="mailto:{{ $data['support_email'] ?? '' }}"
                                        target="_blank">{{ $data['support_email'] ?? '' }}</a>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td class="border-top-0" width="50%">
                        <table class="table-bordered table">
                            <tr>
                                <td class="text-center" width="30%" rowspan="2"><img
                                        src="{{ $data['ic_logo'] ?? '' }}" alt="{{ $data['ic_name'] ?? '' }}"
                                        style="width: auto !important; margin-top: 10px; height: 50px; text-align: center">
                                </td>
                                <td>{{ $data['ic_name'] ?? '' }}</td>
                            </tr>
                            <tr>
                                <td>{{ $data['product_name'] ?? '' }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @endif

            <tr>
                <td class="border-top-0">
                    <table class="table-bordered mb-1 table">
                        <tr>
                            <td>{{ $data['policy_tpe'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td>{{ $data['vehicle_details'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td>{{ $data['fuel_type'] ?? '' }}</td>
                        </tr>
                        <tr>
                        <td>
                            @if(isset($data['vehicleRegistrationNo']) && !in_array(strtoupper($data['vehicleRegistrationNo']) , ['' ,"NEW",null] ) )
                                Registration No :{{ $data['vehicleRegistrationNo']}}
                            @else
                                {{ $data['rto_code'] ?? '' }}
                            @endif
                        </td>
                        </tr>

                        <tr>
                            <td>{{ $data['registration_date'] ?? '' }}</td>
                        </tr>
                    </table>
                </td>
                <td class="border-top-0">
                    <table class="table-bordered mb-1 table">
                        <tr>
                            <td>{{ $data['idv'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td>{{ $data['new_ncb'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td>{{ $data['prev_policy'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td>{{ $data['policy_start_date'] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td>{{ $data['business_type'] ?? '' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td class="border-top-0">
                    <table class="mb-1 table border">
                        <tr>
                            <th class="text-center" colspan="2">{{ $data['od']['title'] ?? '' }}</th>
                        </tr>
                        @foreach ($data['od']['list'] as $od_list_key => $od_list_value)
                            <tr>
                                <td>{{ $od_list_key ?? '' }}</td>
                                <td class="text-right">{{ $od_list_value ?? '' }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td>&nbsp;</td>
                            <td class="text-right">&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td class="text-right">&nbsp;</td>
                        </tr>
                        <tr>
                            <th class="text-left">{{ key($data['od']['total']) ?? '' }}</th>
                            <th class="text-right">{{ $data['od']['total'][key($data['od']['total'])] ?? '' }}
                            </th>
                        </tr>
                    </table>
                </td>
                <td class="border-top-0">
                    <table class="mb-1 table border">
                        <tr>
                            <th class="text-center" colspan="2">{{ $data['discount']['title'] ?? '' }}</th>
                        </tr>
                        @foreach ($data['discount']['list'] as $tp_list_key => $tp_list_value)
                            <tr>
                                <td>{{ $tp_list_key ?? '' }}</td>
                                <td class="text-right">{{ $tp_list_value ?? '' }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th class="text-left">{{ key($data['discount']['total']) ?? '' }}</th>
                            <th class="text-right">
                                {{ $data['discount']['total'][key($data['discount']['total'])] ?? '' }}</th>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
               <td class="border-top-0">
                  <table class="mb-1 table border">
                        <tr>
                            <th class="text-center" colspan="2">{{ $data['tp']['title'] ?? '' }}</th>
                        </tr>
                        @foreach ($data['tp']['list'] as $tp_list_key => $tp_list_value)
                            <tr>
                                <td>{{ $tp_list_key ?? '' }}</td>
                                <td class="text-right">{{ $tp_list_value ?? '' }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th class="text-left">{{ key($data['tp']['total']) ?? '' }}</th>
                            <th class="text-right">{{ $data['tp']['total'][key($data['tp']['total'])] ?? '' }}
                            </th>
                        </tr>
                    </table>
               </td>
                <td class="border-top-0">
                    <table class="mb-1 table border">
                        <tr>
                            <th class="text-center" colspan="2">{{ $data['addon']['title'] ?? '' }}</th>
                        </tr>
                        @foreach ($data['addon']['list'] as $tp_list_key => $tp_list_value)
                            <tr>
                                <td>{{ $tp_list_key ?? '' }}</td>
                                <td class="text-right">{{ $tp_list_value ?? '' }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th class="text-left">{{ key($data['addon']['total']) ?? '' }}</th>
                            <th class="text-right">
                                {{ $data['addon']['total'][key($data['addon']['total'])] ?? '' }}</th>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td class="border-top-0" colspan="2">
                    <table class="mb-1 table border">
                        @foreach ($data['total'] as $key => $value)
                            <tr>
                                <th class="text-left">{{ $key }}</th>
                                <th class="text-right">{{ $value ?? '' }}</th>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>

        @if (!isset($is_pdf))
            @isset($data['btn_link'])
                @isset($data['btn_link_text'])
                    @isset($data['btn_style'])
                        @php
                            $style = '';
                        @endphp
                        @foreach ($data['btn_style'] as $key => $value)
                            @php
                                $style .= $key . ':' . $value . ';';
                            @endphp
                        @endforeach
                        <div class="text-center">
                            <a class="btn buy-btn" href="<?= $data['btn_link'] ?>"
                                style="{{ $style ?? '' }} line-height: 14px; height: 37px; width:70px !important; color: #fff;"
                                target="_blank">Buy Now {{ $data['btn_link_text'] ?? '' }}</a>
                        </div>
                    @endisset
                @endisset
            @endisset
        @else
            <table class="table">
                <tr>
                    <td style="visibility: hidden;">Fyntune</td>
                    <td style="border: hidden;"><a class="btn btn-primary text-center"
                            href="{{ $data['btn_link'] ?? '#' }}" style="margin-left: 230px;"
                            target="_blank">Buy Now {{ urldecode($data['btn_link_text']) ?? '#' }}</a></td>
                    <td style="visibility: hidden;">Fyntune</td>
                </tr>
            </table>
        @endif
    </div>

    @if (config('constants.motorConstant.SMS_FOLDER') !== 'sriyah')
        <div class="note">
            <p>
                * Premium is subject to change based on selected addon combination, previous policy details or vehicle
                details. <br>
                * Quote is valid till midnight only.
            </p>
        </div>
        <footer>
            <div style="margin-left:630px; font-size:8px; font-weight: bold;">Date & Time {{Carbon\Carbon::now()->format('d/m/y H:i:s')}}</div>
        </footer>
    @endif

    @if (config('constants.motorConstant.SMS_FOLDER') === 'sriyah')
        <div class="note-sriyah">
            <p>
                * Premium is subject to change based on selected addon combination, previous policy details or vehicle
                details. <br>
                * Quote is valid till midnight only.
            </p>
        </div>

        <footer style="text-align:center;font-size:12px; font-weight:bold">
            <p>Licensed by IRDAI | Registration No. 203 | Valid Till: 26/08/2024 | CIN: U66010KA2003PTC031462</p>
            <p style="margin-left: 170px">*INSURANCE IS A SUBJECT MATTER OF SOLICITATION
                <span style="font-size:8px; font-weight:bold; margin-left:85px"> Date & Time {{Carbon\Carbon::now()->format('d/m/y H:i:s')}}</span>
            </p>
        </footer>
    @endif

    @if (config('constants.motorConstant.SMS_FOLDER') === 'tmibasl')
        <footer class="text-center tmibasl-footer">
            <img style="width: 70%; height: auto;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAABNcAAAAcCAYAAABCi1lAAAAACXBIWXMAABYlAAAWJQFJUiTwAAAgAElEQVR4nO1dS3IbR7at7ug5+FYA9gpIR2NOeAWCVyB4oqngFYhaganh40TgCppcgcg5IwyswMQKmlhBv0i+k1IycW7mzV9VQdaJYFgmgar83H/evPdv//rX/553XXfSNcbj47t76Q2z2XXKGJ4eH989BZ41bz2XruueHx/fbYT3m3mcJzxr8/j47rne0A4RWJPgWvqYza5Pu647Ff6cNQ9nvfwxPuOZIt2QZ9SCuL+1x+49z11fDR3fp75DeOdc2Nf7UvoUePvgmQHaUtMooXPVPjrfZWON7qUw9pRxq9Yo8D73+xuMWT33yNhEns+gb0bTKXvExpIrdyzfMr0j7nlEBkZRwq/e+rky7xn73uWsB5Gf7hyf8FM0du99rsw5xVzs+JNlTi7/BeyOr9/VPjugg4rkQAV68cdlaaWKfIi8P6SXU/eY7pV2fYSxpNiS/vfte5uvYwi1bDuLiCxw5UwV27HQRjp4jvD3VDuA6qkcm8557gmea9d27tDQE2y55P3qDmlA2q8gvzXiry6XDoXxSfo6aAe30tlkzu4aqvVmj7zB9ihLfgV8H4sNaO5g7wt9dK1vFqSXCn5Nkc3vPStpXwJ2S9Je5ujn2LxLeY2g2rMT+LiKzvtH13VXXddd5A44AX8LTOyPhOdsI4GUL60m4OAhIFQWXdd9TnjWp67rVlVH5wCCTFqT2Fr6uOy67q3wt58dY0E7LjPvN5HP7buuuzXvDhD2eeV9D+1v7bG7WHZd9yFhnB+cd6wSnZUl5nAWej4+e4c55DgRTL4wWpHmHtwLDz4NxPbxBGtgfiahB89m1+Y/N8I6s7F/BL9ooFqj2ezajPN35TPtmM3e3T4+vltrv+dB5PnZ7PqnRJqgPJrwHLbOarkDo2QFGR3c7+4b/149Pr5z9zGVR31QPRgZx21M1nif33Vdt8bYYw6VWveCnrZ49jojiBeSOXZ+VubcFMrNIP8F5r73ZIb22ZIO2pl3KdeKyQE1vUCeLfETkuv283vwjtnLW+17EhCyhX7DfLWgdupsdv3b4+M7zXPY/hzsYwJPfKUJ8MUD9PBtjWCCBhirZPfcgA60z0rSLd1r+rHzDtG4Geu/yTN+SaQ9po92jjPE+LWmHZBkb8EhlHTohffZB8hs1XrMZtfPGj3mfH6LsTO9sGB6bTa7/lVpO1wJc/xJOz6G2ez6Ens6DXzsg2OfMZ1RVWdn6s0HyFm2lrV4w9Dve+93Lm8weZFiX2v9BvfzhuaWnn1X4qvFeHkJfgvSS1fu15Ta/C5S90WM2cxm1/+TYJeF9POvsPN8xOZdyms+aj7b5+Mcndc5uj5oA/+9YKC1oDYAgDMIt7EiNVCWOv+aOIPy12JR+m5jyMBZ/KJ0GCdQ2n9C0Q6GgrFvwMgtYN/xpOELZw6ftQoSc615GpGCCyjMqsBabSCstQbq2z6yfAPIebfZu8+z2fV9Iq9bhHi+1r6kONnJcGj+D+yhdr8nlTNic5G671PQ9VOEd3Lo6QwGyUarhw3dGfpLlDnNZD6caclpWVXORJq2PDzrvtG34aH/YG+0azyBfGhlg/QhOy6xn7WQ+6wLrL2h2XWmrE1FaA1TbbWceVv6+QxZI/IqggR78qfUcTKHMzswDBn2lGAHvNhbysCa4ck/AwfSPgwN/Ru6WrMf6sAacBbQC1fC/kRlF8bK9vEmV5aafZnNrq19FgqUuOjLPst5xwXssAM7vSJvsM9XOTSZza4XZuyJOrxL/Gw2oAM3GJ+WXtQHliNDyO5KoZkQbw/l7/WJUl3/HyPjJVk9huBajuE5ZEBKBAyqVGEyaRE8SICKGY1wzVDm/jNOcdKZK9TMCVVuBk4RnGBMztjNuv3eeOzmHZoAStb6N8ps0EIUYDlw6FCrhC22fWUlNMAF6EO9jgqeLw62A00CqN23LNOnApkzJN2XYgKDvsXaGt75IxZgc+RmbnZ8C5m/Fnj/Y0GGZwgfWgVbHPr2MxZSUJ3GIWdCPJd6sCdh0jo4nwF7oFZLPkoIZZxMenj/q/eBzjeBfWV0lpI1cy7wbRbP4nn3GXbtXeS51tHP5ckL7WFpJg70AoKFLDh6prjGxzL+9rmHCpAd9xm+1G7IK9pKTGGH+Xs7Kt7wnr1GZl2qvWyw76n8QA69NB9bIxTb5Jmxih84xHvpoHnQ4FpAIMQwyuBawQl1n0aQD+1axj4Xc7JiAnCLK7IfYbywkxyDt30H2BTBmAeM+yP+LSFn7PbZP+PnF6wTW59JSJniZFlaf3cOd0gntwgakz1gkpluLWEtKKi9sw43+Le7zmMMtOycffsI2tgKn50mOqIxuTSt6ABUDaB234KDXyLGyBb7bPfb598qdcYa4Ibs+054zeeEQMbekcMamSbyhMJ5vXPecdOHzEcGMQv63HnXf2ujus6Ccxyj786h8QeBRlrQuMamqWX3vG1ca3fvrJ/ltYeAnO2wJ/9ueGigcY5K13eHed4FdKKPs0BWK5MVKTqEreW2wEG+TbAD/O+FoLVzQ7p6knoYBvh6IWRLv9K5uF7N5EPoev2J4PcESxJEINlnHeHBsdhnKXqTHQiMjTdegIz/UObl3pERN0TH9LEnVxG/5tMI6aUVtHpwyJjDmLEXdJ5kW3dSwPwfA08yNxj1ku3V6JS5BLkE+8YYS42yYmLMZk6mTiK1eWKn0J0ixfJWCE494E4+KxC9Eu5YG2N649Ra2SDwJIEJ39+cQoU+/LWQjLA7XCNiY78UTi7N2G8TMsHuidN3i0AZM+IuArTE+M0Ydwuh8OgpvjOGAMN742BXMBbOhSwaqZ6azQ5ZjlQZr1lQAPNcE/ow9KetZeXLsy153jzARymwAdQqV+ic+TPs8C6xThC+fyCXBHxsHJhhWJMCratAHYmFMrBq5OrBHkAWXBE9YByAhS/PnKuXarnZfTsA0Mj8ZGBP2dpseziwu2DrlAvIJKleyt6piycVRZ6DJs57sjuY7FhWzDq7aniF29AdtaPAF8tAva6Xq2C1moE4YLbmzrOxSh0oqlu61/UrmeM9gY3yqtagoX3ULPPXaaHUIWw+uVlrC8Ee/cTkn/OdZcgewlVQ5ujvoE9Yk5y5kE07gQxVB44fH98dyDHIYlYTbUJ48JLIlQvsJdsjRve7XH0IfmK+Rsg+s7SYIltr6+yQ3rwV7PSvazom3rDAgZbk990hgBoqEr8gvhTF4+O75Fq03bf1ZTLoDvwWopexxQ9qYKK0M5rYO+CpUDD+v+Q7WXsPtODjHF1vD0O+2lNDB9dKlP9yTMwRUNZaLCtn54TgC/FFZC2LjDSc3tKABjMGOidN3bnn7+MSwZZnfDZk8DABr+1EKhXvjI195dQA8HFVGqgx73CuBPmMbuuIuPOYC8b/XAowQEg0rReUiKsUQ1MAo+WttJfdty4zY81gojBGG/iOFd49oA8fAr1cksK7NR3kKgFUQDr9Fo10FxjDmGhfBRN8gi7y5a02uEZhZAHoaUP03JzIs5WgD0W52cE408j81PEH6qztJSO8Acze3Je+K1IzTnQqXPQg03w5a9/l6tKzioeK5lmrkuBrDjD2SwRVJGd03aCGjc9DD5DprqOpdbSSAfm4xLzZwelUOCxhZSkWMds3cMsld27MDniQAmvdt9IYsUxddqC6jdhZ906WLwvCFO0h3ruEcxjUCyZhQbDXV4JDzn5Xojdp0DiiMzZjvc0EvSnZ6X7gjGWJDcEb1p9lQSurL4PPBs314Z8zf2AX0oFjppcYhAxt5s+H5JSf9cyCui0zwY8Sjq5fCwFze3PsZe0GuxYKAV5Sw+uip6KxWpQya1/MvicnIbHgmf/30DUhBqYcggENC2QnfiR/mvS0ZmzsDwlj/0T+NK1xXQTKgwUi2Ak++91DT05lLnw6q1GbS3td5egBI4LxqibDg8mEe3KNpaR2Eku1LnaOQSNiQHzkNF8DTYImAYP5FT0FrgqlyM3aMl+qs+Z3MqsJn75rNTe4lALHj4/vFkPTt1CrUQrm5Rrx9Ppa7avlWuCQbyHI2yr63kK4Eiqtb9PrP+CduXD18D3RDUzXanSIdCiWG5hl7yuVm8xW3ONmQCzY/RyQbbUO3ZluZevA3vfW3yPQtC9THwqDuYx/jzrDKCHQxOhvCN7oBFrZI0g8JnuZ8vFfwMZzUerPH1XiwNAAX82FK/0XNgD6d2zMg/ATqinhYx94DjM4ahgb7BmhMaTMZxd4zitiVl6bjGHauG6IxYYw0xvJKBXmxhQFVQCBjL4UJyO7k1EJAgHgFGPnsvHYSxzDsXeEYUK/tDYX++733BknV3EeXAkNZIjmOnBr4iTXCKAy/twdYyZaRdS6+qehJ0luqvcVqf4sgJK8h6Anpp9/a+woMDosam4A2ceyY1SBy54gBebZWufKjidkoboYQ3ODRQ+2Sh/rqwacDYn2/HlL/BazfatfeyPItisC1xmvtEEOBCp9mu4QYKlx5ZkFHA7mjKxW5rP58ozJtxY6dgwdu0uhCfaMgjcCPtvlkTQA+B7oJQW+TRZrZqOVyT8gIHIY8vL7v5s0aHPHlP0kCsqN9Bz/DquQnpyDg8lFxpAyn3XgWf5zahm1LYxjJphTHGR/TDuh1p3kMEinKmqnP3DyM22cvVhr7NJpbY1TdqZI2PiYUpzi2utYwYy80uYGbG3eNuzMNTTYvGJXQtk1g1vvvy5K5Bbby+wAauCKxOVf6DST7Uet00mmT3x6kq5cpQb4imU+6IEFXG56uELIAkBdYVAgaNCNBDQwD73pB57Egz0FpAybwa60BGyVWt1RO7K+puudzdbwD5AnfawHgtTs8Hrufe5ZaJAkOoOB5g0lTiGTRcsCO0AafyqvS7ZNjT1kfCYFS5hduLC8KgRgbhoFX960agzSI6IybkS8wdZ61/eVeyWYTXf2HdCLBEZHbF8ozQj28Y/MtQwEDkNe1n6oa6G1CH/ac7txCbXm87aPaw1CcEgbXEsV2swoyBH8kpHS0nBkz85xjKTvFAV0AsX5mYGzEU7UfzcNFkZ2xdoFMzbfFxjBkiL5wxRTH+paUQsETtNjypTJsxeeFRzkbMcRgXo/Q6kkgMrk2H6EzW+qw9Au6kH4xtO24vw1gTsmk3qX+eBlVntv22MWI6PjiwK7hc09J3DZBMKVUHcfa2W+nmDOzLgd2glsou+7bzTt85e7puzdfdnI9BBR+bnQzY9awXoXjA5tUeocO6AKX+Lz0SBlJpjspsEwwXGcOHLTl5/7SjJV0hOmMUj1juJ9wCnu74PRYA3eKL0SWstn6wMhell/T/Y8cKBDEJT1kxC0/rylFf/7f7Xsv1ww+jMHWudDNTSoGVUOFu9rDTj5sZboqfPpwxG884T2gQAP1PZIAcsiSY6Uo0A7+1OToBDmzq42JZ/MoWAt+9M899QAdMfo/hPL0EEDhCuhE98bnA7eIWNzNIoUa/dAHIqs5gaB53VYmxUCFLUK6w+CQNHzG4Xh5SvmnbcWUuHdVKfWKnDWoSy3uQEzClrv4xxdLmO4r9g1cOllpJxiD3yZta+lb4XA3atM5kCWTI7cfBI6qGll/iXRX6oaSJVwDnlzQ/glt7nB2B2fWKb8rVDQPtXmsft6Sdb2DB2R++7g+4KArSLp7BQwp+nW+7ffEXfRUzD5ntkXRiZ4cu+WNSwJFO5n8qvIRkbh/kuhQ6drB2ivdWpvEGjAGhsU2bm4oeDz3T6isxlvGb1zS+ynKpnh4B3JPnvvvP+q0D7rRWc7nVrZgZcUXCvljdKO2szvaapjlHvR+TIdNoLvy1q8RcLKTQV6GTvuPZ6Rmtn4+kOSoyX18PvEELa3C4mmTnoPrsH41nTVlASsj7foEjXUlR+t46KdT18tgg+6NhFmZNcP1EI24GjlnqqwNWyVcUWfW8Cgfpt89Ti8dZzDkGPKZBvJ+LnCnkrBYBtk20EZjSUN3Mzpi/e7l9pcmRk5K9A/UyATGHHvYeRdNhLKteDTR4c9ZnWvoqfLkSuh7v8fGN0ZRt3LqWLA0ckJoLKTytb7d5FQ5qDWWFgnLx+qzqgeTgg9nQe6f6r0XwEPbTJl/lzK6u0xy8vSInNSbXOD1ABQ8oGP8lrgcyXnQxOY953Hl6uhOTYcnKtPpA6dCY5cDWgXau29VASDa1gP39YwNzzOe3AutbXFngWH+OCgvNG1N/d9tewAJhtz5Qy96qb5oiC7pQY/q5AsBC199AKmU7L2ta8MLoTumh1+Z4MmW9ipOTZgbZ3N9OYCPz5tiAdeI+ANmunVgw3MDv0ZmL5cCgFpixr0MnbckjV81cVdadsfG4awvb8CMpL9aT7EtVBtMGot3D8veWYLaN+9FK7l+ahVvNRCIjxN8VtfWYQYUW1IVnZujqUYfe6c3yKwZH8+BAJrYrv37lv68FzR7XWK66JPQ9avsYBiZ7Ig65qA0+GMFUx3YWj6i8kwGfG1WZ8+vsAp8A3TXYw+gGiNRgTYq10NBZgczWlu8COd/f9h9yeVP84IPf0uOAi/EqO7j/XX0Jmkj0quZGYB+o51P01qblBgF/j7yX5qdOmNGu85dYUUYE2DxtDcoCqE5lJ3RKbXroupQqJdx8bIdA+jCzbnnPGGOp26sHbAbaK9kWvzlQRBmexmQYfflEEG1kzM5/GqtOXYqbEGdGe4/nc/gpq5TG++F/TmPBLo1vIG+10pbxzdNcoMetkccY1lKemDlf3xZSc7+BpFOYnvEb0G16CYNCfuewgYbVR1kOCaUF+Ewd5r1s6neQq/UNvBF9a+IeeOP6Xz6g+0hTGUzjVKFYWlzT7/pjAqpzAqx1AclPHExPu9miahjM4Fp9eHMa6PWSF/xBU1jdHu77WUrVq1M11Ch7IYjiWdvTXsCf+mAf/egZ6Yc3YMxvkQtVikjtcp9D32tdVe36stO56FQNr31qhGW7dpyLprWjAamJL9anoNGjrxVKjd58PYw09HTlPGRvpJm2kW4C2LhxZZTU7g86My+DmGAFsMRm+eKuwwLW/Erojn4ChpG37NuZJezkAvx9j0IHQY5++93/zJp5cgrXyH9ep6Rd+Za1oFf+t0WRwi20sLLXNa5aRNR21pCN0L/+5cAU5O930neyyd944l8t5SUF2m0j+Mq1ME2WIZXJ+HzmALZH+sHAWSRJNQyMa5/SeeHVoHW+z4WDIlXSw02T7CNQNJATODupRGmDydautxABp9URs7BAZjPzVlleHbn/Fj/v1JCC5PwL81dco8YIT3IY9TMjukNek1qwn2DDsgSOluOfZut8xmYHtFi3aXGPOQ40x+D5W91sIeVTnTWHN/LZhzXhUpNoLQ2fTVHIVMPTrnEsAOWMIO+BTRIUZ2aDPYcum55T6dZTz/KmAbNQtQOPbZaQP7bAidfa6xw4QC9V1PvDFUCRTNXsRu3HQOvcT8GmsXHfvhiytDxWZBgm0fi0ccw9oMwcca3Pddc02bkWU70z2jeKUm223VZwZboBMfw9dOe6QeBsOkoJ5UCtakVskczktSlFsAdQZIgVstGLP3yjRm3zNTaXObXjw4QvME++M/66thkZIS7pxKXkHJrAK8th7BFdwrjNHNTrKdJbN5H/t5iSClLf7MrpUVv6sBXPo4x48vX87gEMSyG5nzZpx/jfztSmondYr6ScrH5NboKsF6gOLpG0eGfpWlcHBvWcdGpXzYOUbXKeiJyZs15LgfPKGysUBusr3T0tfeKebu09RbNMzozZlAbcEVWc9LTWC6oKmP75ScVG7CJF0JNXbMfxMeU9rMaSnU5uz1YAiOb5VmSB6YvfmnQBMMy8a3IlLr1K6F5gtWlra49iYC8mkFfbPEejE7QFsvsUYDCwttVr49gDzFDxu/CSo8a+snww9bk5pOvXQpxn5b+2yOf0v22Vp5yFdbZ7t60wbRfBk7tRl2inVjTSMG443WNRtxm6bWs1y/Zg4+lmzYrMZoI4K736ye6VyYo18L9alRjdDWGML2/oqQbdFb5lpCV00/Q0qrnBY9pzFqMwF8gdfbVdeYUSmccM69/1pEx+2fGgWEcXJEPNC9s5Vyl8aeLIgDpyMax850ObnEzwqpzz8LNWayDWezVzi9/Uk48Wl+8h1DJPujSgDFyB4o+l+EE+y3DeRMydhd+jANSeypnY+pgj5qBA1LM6Sk+kkrJa8z4/KYjackwJFlezBR7s2TQ0/LiLxhAU9pj3Jk/olwEKUx8m1tGxs4ZzJtiOuhjAdtAEhD32weMT0/d38aBVhqyI4iPo1cLe+zU1ztRk7sJkEOWl8NZfMOZY8wm/LM4Uk23l6KkZtAuFM+g8HnIUZ3ufYSW0dtswhXds+RjccCc0dZj9DwOObFdFIHWTrEIfCTZ4eF9KYmGJDDGzUCudX09xgAelmOkF5yIe6DUM/UyhKfXnz+/1F7LQ90Pwzd9XktVGt8+QVwtVdDtc5DLSRl4TnQGgd9Mb0f4Jnjva5To+0SysbLFHuOES19p0nmAQRVsiMjQPpOlsEPJ4LRX7HT5NS6YBi8bguyOZkhW9VhBL1LMktjaKTwbs1OY/bKL71CK30n0HkqFUU0EgigaoNrTB5Mx9CYoy9APjC5m2UgBwJ2F37AHcEspq9z6EL6jkZuru3hToCmNAHnqsBasvpOWvpmc+/7YJGhBn/V0C+0MUrPziEbg58pkIoaa9PsgAz0p60J9wKh7m8XcAiTutXXAHQpC7BNPPuc7W3OQeyJkEGSZedijdm+TI+05tQLAjqpG0uTs4CdHj0MVvCGT1v7GjedAvr7aGml+7YXEj8ek20Yqynsy4kJZH5ysswPqMD4+8U/HX1wLfC7kncUQbgCoZqPkC025Hz8tWUZUCWMyIyCNxmBQ8lgbRlxZ/POyVxiDLgtSeGGIj04Fatxwo01jdY3GBBsPZe1axIlBPYZDaqcGMnZqUDX7GR6Eggy1QqcFtVO6uQA6kQpDyVZNVjq+EBgvJDtWAcCdtoT9JwAUInMfzV/8DKTaR8GyMhdEbnyRukUsrUtylouRcXAfLH+CtTm7NM2bFELqZZ8bnVA5pdrsIg5/Ozvc6yj/7xBHMJA8X+XX5mdO8kIYEl8nH2IDJ5gAf2xNblIQu1DpEZjXAv+nmbtQ7wR7MpcCPas3q/X1wZ8b0Yvx1hH2ULTKdqXzVulDfWXOZDOAWwVFgd64dtegmsJXTVTOtMx9JXtpTVkpTvw2hOGFgbhK6YS1tt/L/uM1vmXDBN1Wjroh53m9VGTjkHtqMO4YgxYIy2/RVH50QNK8pM3zkmjuWsCduzU+kwpixiPFwc2hRTxLvH0znSh/Zv0gysnDDWMdnriG/tS4MT34phP6iuhNPjM9IC2m5/2OswLAjK/RG4uhWB5L1fNLJwaQj40tQ2lgP8QQUILxu93IdkB+ZHrdMbACrBr60ZmA8FjiZay6Vawn3eK9e0loAK68+tx2THGgkKSTGnaJbQ2Agdxl9pDBXxOOogtvdasld3HBqbTxnbFLXfth+INSYYNUUahNsbeFCgVr+SCYP/6Mr9Xe+d7REDX7y0v9pW5VpK1ZhWXNturjxNcdddT4fda4p4WnuRqazf4TrhryO2EABx7zoHgDWRBvdE4uzDcJCJuWjcCBg0b+/uEsbMx7ispwybBNQiOwZtHRCDV5qoGcj3aws+KkbJRg/SJdWZ0VMtQolfIhHEkZ1sEAlk1sic3gnOogRTE+fxXCLCBbllgqtRBY/Lm4D0BfZ0iNyWZn20Ygl4ZT54ldqQtBrJhtEXK3Tk8B+TK/UDZBbkyjGY4lg4mcA24GSBD74UMvk+FmchJ1y0dSHWbqh1Ag1elsUR5XdAhZ2O4EmoR4ClfnjK+nCbYqWvBhml1EDs55m6JAT03tgBKlp0+FG8EalfahgxHGWCrfeW6byTodt9G0mQAf29Bx2ZwdB6T1Zc2oap5cC2nq2bG31w0TXWGc6B14qVgoeSUMvSRul1j3btAOrZkZH0OOTUQJlEibgzJSP+Mzm85Y19VGruUMUWV32x2bboxrkPGNb57JYx7NEoo14Eyc8c6rEJGAtaI0b5UO4c5/W8kGnGcsZyrNFqw/WL0wWSMNnVcmncNA4xdn4sicAWwA9/eapyKSkXEe0WAbrtSupKyUASjLyTzc+VmscxHZymme1cDFDbODQBJgTmzZl9iMr4mAldCNbqC0WOt0gYhGVANRs7BJnwS1mFX4Up6bvFyaQ+K1xd61OzfHwKv3iR04mV04DvBVQNrRs7ADlhG7ACp4ycr68EyJjuUErkN2GWGhm4FP+mhUi2t54r1j5sBdGW6k15G7FRJz+377ACtBLMXtYHN3nkDkHSTkXFRvum+7WVT+gLvaOjlRJCH+56b3fSBEP1Ldv33tgbVAVq7DByiPbglBP7Rw5i0ijwWfV+TdvoML9leDU+5cruE+mBtyBmMYq4VjJEQYsYqRTJns+vfhPl+gHG6dsZxjnWWWgPfBepgVIUJpMxm1x+Faw+/w1FMGftNDWOpg7MrtOGf+wrXycJ6C5raYszP+O8pxi4Fj2/6aLueArOOoJ2UFtL2nvzv2L8HKBaXv6S6OV3k2g+rO/M7nMW1k/m3CKzzx1q8nkAfuZkRHZ7D+HpRIZjzDGWmkZPs/Rsh8/ANAoBbfMala5u1+UJTs9n1/yj2Y5lgRJquvDWyas69vT3FvkrX3+4q8e8D4be5Ty+gvU+CzrZy88ox6k6xZxLf1ZT55t1fvN9NQK+9OZtYo7uEw0f7vWfIPSkIyWS8xWnlOWYH5qFbd4RHF5UcRrNGf1Z4zqlzCHgP+XAS0RMdHLdFiTyHXDnYY419Czph9LVUZkPNMe9n8Okc855Hauxt0aFPC0mHuKhtz1s74DMC/g9E54X292D9HL70ZUuH5zwhIGl50q6lZAvsK5eGYU7hfGSdQy29f4BvIMkwqdSQdi696Wz4P0zOzRVBjSF4w8rmX8EfPiYRvrE6ZoryLSCrd5AAAAWySURBVFFbcja7TgmIrpwD7lJ6WVf0rVNoalXhqjdFQK92iTb5MWS1pqz5WuF7nxKd1+G/IT9z69tCfQTXtEIpOOkIwfhYthA4FbPwOqXQtNAaQ1mA8N8SxVva4eorjGOEkxrm/E2tcFQ8att35xqT7YC9Lx37XaLRqQEzng+Ca8ShOnP2Ozb2/ZAFsyO4FIxZCf46XCQE57bIfDkADOuVYIxo37FtwOdSMOSFPgJXQlVKOCA7ioNr3Te5sUwtmI79sE66pDPOFM8NXXuymCY0uKmFlIBjTZl5T+hpwbJzjEMC+pLkpnYOVWU+glo3ZFwvdflqHX4osXIchJQ5bCJZfp2SvktReq39lgRgq2SMQjZJAd4UTB0dqdHznaXZCvYTrWeX8H2WEfVyNVQRAE3RjRY3qbZCIPhgIZUmKUGJHXAnyQjIllBQ4r2SHo3NNa98oHlP3j22umshOzWGFPupb519T/RNNLAZsK8sWvCGfbfNgA7JvBjfaAM0KXLGzZgrpZea5SBSaCrnZgf7jqRfmF7tEnXzMVz/TVlzTQB3mqDjLR7YIVrTa6Ew/rQT1xi0WsKodS3JR0oWXixYmNKRMTewwRRniBk1v8sGAkusrbkWpnbJeU/XQV8BY2ddyFLG3uKambaeQ64RZY28Ud7JxxWAlNpcueuwjX0XPJ9LI9tG6xyra8Rocp/oFLZurJEl/zCH80Rn1MexF31+qExXUh0nqm8hN/3mIym4acQX0pXjqz5rygTqwGm+u8GpfAl9ZwPrxByZFLuh2dVQgNXmbIk9dMC80sFk7pXQ2Gdr2yImOPaL4fdMXq1VmiSKAN1qcBcL9MMO+KWA7rYV6ceFVHdtTDquyD4bq51a2FAiFBRoWocQh8kltJwanE/F90ovDCxQKY2f6VVtqZcf0GGPpm+UjlrXXNOeNmszpFKM0BbZTVonTyvwUhobVEnRDAgTNubUE/zoGHG155+JARHjIP5c6TpVNqBofkqs5XLXeOzaosXrjBo0Zo9OW6UvV0RKbS6pLoqEPa5qqoK6jjGifUfS8zPA6GPq0Eep89a1dpAzAqjud58R1P45swbTsXbHMnLnV0nx5yLQvEPca8i+1PW3Mj/XWQ8iULNxUrHmoXYsl4kyyf2uS985QbYdeCvnBJ/teVK2Oz7LZHct2dFXc4MtDg6NvqxSDxY2HzucVsvnQNfoGvbxHs82QbXTwgyaEM+14MePiQGDHeSp6pov1uI0UW/tHFugus0VoIUx1RZdt7TPhoLQTVZrI/XNG6/g0HIqzxjsGzfNyLLnjzCwlgRBr/7oEloH1rY+CZUqiV0LfUrIxJCK5GkipSpF4tTu0jg8Uotm7XzYaYGWOLVGhhVaGuQ4eWtt7SRcM/nZ/13k2T5UUXFEz5e427xAUM5fhyfQxW2FaDtbh6xnYk3mCE7UHjvbq+D+aXkCAQo2bluLbYdxP+Gdt5WUj3bt2Ty19GRrnfhK/OD7cGQvofAXTv0cNp5NjsOA79zCcJo747pwAgw561yTPk6dMfh8njRnpy6hD39e/mdSeGMlfF5LIy792z2RjL4Nfu4J/5YWS86RO35NR38vn509tHSroSmmDzXjW6XW43DW/xzrb+s3nWL8z079x+Zys/t27eUkIju1z2ZrmUIrixInF+v78j5kosydumBsTBvQSYluzaUfHytBh7pQ2zI+Avus2UeWGfHs1OncJPBbKk7I+j5nvOtKYWO7a3Eq2KVPzs+mZgAooENidqiPqB2B9bN2wNzRB5IdcJ9TJB/vWaJchLUFTh2558vsVFsjJ0ue0YKGZ7PtsxR49tlcsK87Rz+XNPbQwp9nid7056IJ1NbijWx5ncgzz87+sLUvuQH0aszIEl1r6QVjqiGra9KUdl9SeXDu7Y9IK7jOnjuGlLUo2fvSNfe/78oOZisZWJ33QtNqPdB13f8BA073CeF/xS4AAAAASUVORK5CYII=" />
            <p style="font-size:8px;" >Composite Broker License No. 375 I Validity 13/05/2023 to 12/05/2026 I CIN: U50300MH1997PLC149349 | IBAI
            Membership No. 35375 <br>
            Corp Office: 1st Floor AFL House, Lok Bharti complex, Marol Maroshi Road, Andheri (East), Mumbai - 400 059.
            Maharashtra. India. <br/>
            Registered Office: Nanavati Mahalaya, 3rd floor, Tamarind Lane, Homi Mody Street, Fort, Mumbai - 400 001.
            Maharashtra. India. <br/>
            <span  style="margin-left:140px">A sister Company of TATA AIA Life Insurance Company Limited and TATA AIG General Insurance Company Limited</span> 
            <span style="font-size:8px; font-weight:bold; margin-left:30px "> Date & Time {{Carbon\Carbon::now()->format('d/m/y H:i:s')}}</span>
            </p>
        </footer>
    @endif
</body>

</html>
