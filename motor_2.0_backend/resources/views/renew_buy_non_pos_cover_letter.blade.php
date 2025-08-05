<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Covver Letter</title>
    {{-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"> --}}
    <style>
        @page {
            margin: 0px;
            padding: 10px;
        }

        :root {
            --blue: #007bff;
            --indigo: #6610f2;
            --purple: #6f42c1;
            --pink: #e83e8c;
            --red: #dc3545;
            --orange: #fd7e14;
            --yellow: #ffc107;
            --green: #28a745;
            --teal: #20c997;
            --cyan: #17a2b8;
            --white: #fff;
            --gray: #6c757d;
            --gray-dark: #343a40;
            --primary: #007bff;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --breakpoint-xs: 0;
            --breakpoint-sm: 576px;
            --breakpoint-md: 768px;
            --breakpoint-lg: 992px;
            --breakpoint-xl: 1200px;
            --font-family-sans-serif: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            --font-family-monospace: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            font-family: sans-serif;
            line-height: 1.15;
            -webkit-text-size-adjust: 100%;
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
        }

        body {
            font-size: 8px;
            font-family: DejaVu Sans, sans-serif;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            text-align: left;
            background-color: #fff;

        }


        .mr-1,
        .mx-1 {
            margin-right: 0.25rem !important;
        }

        .ml-1,
        .mx-1 {
            margin-left: 0.25rem !important;
        }

        .mb-0,
        .my-0 {
            margin-bottom: 0 !important;
        }

        .text-capitalize {
            text-transform: capitalize !important;
        }

        .text-center {
            text-align: center !important;
        }

        .p-2 {
            padding: 0.5rem !important;
        }

        .bg-light {
            background-color: #f8f9fa !important;
        }

        .mb-2,
        .my-2 {
            margin-bottom: 0.5rem !important;
        }

        .ml-5,
        .mx-5 {
            margin-left: 3rem !important;
        }

        .rounded-circle {
            border-radius: 50% !important;
        }

        .pl-3,
        .px-3 {
            padding-left: 1rem !important;
        }

        p {
            margin-top: 0;
            margin-bottom: 1rem;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            margin-top: 0;
            margin-bottom: 0.5rem;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .h1,
        .h2,
        .h3,
        .h4,
        .h5,
        .h6 {
            margin-bottom: 0.5rem;
            font-weight: 500;
            line-height: 1.2;
        }

        h6,
        .h6 {
            font-size: 1rem;
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

        a:not([href]):not([class]) {
            color: inherit;
            text-decoration: none;
        }

        a:not([href]):not([class]):hover {
            color: inherit;
            text-decoration: none;
        }

        table,
        th,
        td {
            border: 1px solid black;
            border-collapse: collapse;
            border: none;
            width: 100%;
        }

        td {
            text-align: center;
            vertical-align: middle;
            padding: 2px 10px 2px 10px !important;
        }

        td:first-child {
            padding: 0px 10px 0px !important;
            text-align: left;
        }

        .text-right {
            text-align: right !important;
        }

    </style>
</head>

<body>
    <div style="margin-right: 5%;margin-left: 5%; margin-top: 10rem">
        <table>
            <tr>
                <td class="text-right" style="padding-right: 2rem">
                    <img src="{{ url('renewbuy/cover_letter/Picture1.png') }}" alt=""
                        style="width: 120px; height: auto" srcset="" class="">
                </td>
            </tr>
            <tr>
                <td class="text-center">
                    <img src="{{ url('renewbuy/cover_letter/Picture2.png') }}" alt=""
                        style="width: 100%; height: auto" srcset="" class="">
                </td>
            </tr>
            <tr>
                <td>
                    <p style="font-size: 12px; line-height: 27px;">
                        Dear {{ $cover_data['name'] ?? 'Customer' }} <br>
                        Greetings from each one of us for being a part of the RenewBuy family! <br>
                        Thank you for choosing RenewBuy as your trusted insurance expert. Be assured that we will do our
                        best to provide you seamless service. <br>
                        RenewBuy’s SMART technology offers the following benefits: <br>
                    </p>
                </td>
            </tr>
            <tr>
                <td class="text-center">
                    <img src="{{ url('renewbuy/cover_letter/Picture3.png') }}" alt=""
                        style="width: 100%; height: auto" srcset="" class="">
                </td>
            </tr>
            <tr>
                <td>
                    <p style="font-size: 12px; line-height: 27px;">
                        RenewBuy is India’s fastest growing Insurtech platform, with a vision to make insurance simple
                        and accessible to everyone. Our distribution network is now available in over 750+ cities across
                        India. <br>
                        For any kind of insurance requirement/assistance in future, feel free to get in touch a RenewBuy
                        representative.
                    </p>
                </td>
            </tr>
            <tr>
                <td class="text-center">
                    <img src="{{ url('renewbuy/cover_letter/Picture4.png') }}" alt=""
                        style="width: 80%; height: auto" srcset="" class="">
                </td>
            </tr>
            <tr>
                <td>
                    <p style="font-size: 12px; line-height: 27px;">
                        In case of any support or grievance during the policy term or at the time of claim write to us
                        at customersupport@renewbuy.com or call us at our toll-free number 1800-419-7852. <br>
                    </p>
                    <br>
                    <p style="font-size: 12px; line-height: 27px;">
                        Regards,
                    </p>
                    <p style="font-size: 12px; line-height: 27px;">
                        Team RenewBuy
                    </p>
                    <br>
                    <br>
                    <p style="font-size: 13px; line-height: 17px;">
                        IB/IC/EDM/CWL/2022/82 D2C INSURANCE BROKING PVT. LTD (CINU66030DL2013PTC249265), Principal Place of Business: Plot No.- 94, First Floor, Sector- 32, Gurugram -122001, Haryana; Registered Office: Second Floor, C-67, DDA Shed, Okhla Phase – 1, Delhi -110020, IRDAI Broking License Code No. DB 571/14, Certificate No. 505, License category- Direct Broker (Life & General), valid till 26/11/2023. For private circulation only. 
                    </p>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
