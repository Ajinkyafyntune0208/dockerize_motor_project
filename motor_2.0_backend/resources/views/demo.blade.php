<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
  <style>
    @page {
      margin: 0px;
    }

    body {
      margin: 0px;
    }

    body {
      font-size: 10px;
      font-family: DejaVu Sans, sans-serif;
      /* border: 1px solid #dee2e6 !important; */
    }

    hr {
      /* height: 1px; */
      margin-top: -1rem;
      border-top: 1px solid rgba(0, 0, 0, 0.5);
    }

    .table td,
    .table th {
      padding: 1px 10px !important;
      /* vertical-align: middle; */
    }
  </style>
</head>

<body>
  <table class="table table-borderless d-none">
    <tr>
      <td class="border-top-0">
        <table class="table m-0">
          <tr>
            <td class="">
              <img src="{{ $data['site_logo'] }}" style="width: auto; margin-top: 10px; height: 70px; padding-bottom: 7px" alt="{{ $data['site_logo'] }}">
            </td>
            <td class="text-center pt-2">
              <a href="mailto:{{ $data['toll_free_number_link'] ?? '' }}" target="_blank" rel="noopener noreferrer">{{ $data['toll_free_number'] }}</a>
              <br>
              <a href="mailto:{{ $data['support_email'] ?? '' }}" target="_blank" rel="noopener noreferrer">{{ $data['support_email'] ?? '' }}</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
  <h3 class="text-center my-4">
    Premium Breakup
  </h3>
  <hr>

{{--  <!-- <table class="table mb-1 d-none">
    <tr>
      <td class="border-top-0">
        <table class="table mb-1">
          <tr>
            <td width="50%" rowspan="2" style="vertical-align: middle;" class="text-center border-top-0">
              <h5 class="text-center">Premium Breakup</h5>
            </td>
            <td rowspan="2" width="20%" class="text-center border">
              <img src="{{ $data['ic_logo'] }}" style="width: auto; margin-top: 10px; height: 70px; text-align-center" alt="{{ $data['ic_name'] }}">
            </td>
            <td class="border" width="30%">
              {{ $data['ic_name'] }}
            </td>
          </tr>
          <tr>
            <td class=" border">
              {{ $data['product_name'] }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table> --> --}}







  <table class="table">
    <tr>
      <td width="50%" class="border-top-0">
        <table class="table table-bordered">
          <tr>
            <td width="30%" rowspan="2" class="text-center"><img src="{{ $data['site_logo'] }}" style="width: auto; margin-top: 10px; height: 50px; text-align-center" alt="#"></td>
            <td class="text-center">
              <a href="{{ $data['toll_free_number_link'] ?? '' }}" target="_blank">{{ $data['toll_free_number'] }}</a>
            </td>
          </tr>
          <tr>
            <td class="text-center">
              <a href="mailto:{{ $data['support_email'] ?? '' }}" target="_blank">{{ $data['support_email'] ?? '' }}</a>
            </td>
          </tr>
        </table>
      </td>

      <td width="50%" class="border-top-0">
        <table class="table table-bordered">
          <tr>
            <td width="30%" rowspan="2" class="text-center"><img src="{{ $data['ic_logo'] }}" style="width: auto; margin-top: 10px; height: 50px; text-align-center" alt="{{ $data['ic_name'] }}"></td>
            <td>{{ $data['ic_name'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['product_name'] }}</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td class="border-top-0">
        <table class="table table-bordered mb-1">
          <tr>
            <td>{{ $data['policy_tpe'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['vehicle_details'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['fuel_type'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['rto_code'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['registration_date'] }}</td>
          </tr>
        </table>
      </td>
      <td class="border-top-0">
        <table class="table table-bordered mb-1">
          <tr>
            <td>{{ $data['idv'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['new_ncb'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['prev_policy'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['policy_start_date'] }}</td>
          </tr>
          <tr>
            <td>{{ $data['business_type'] }}</td>
          </tr>
        </table>
      </td>
    </tr>

    <tr>
      <td class="border-top-0">
        <table class="table border mb-1">
          <tr>
            <th colspan="2" class="text-center">{{ $data['od']['title'] }}</th>
          </tr>
          @foreach($data['od']['list'] as $od_list_key => $od_list_value)
          <tr>
            <td>{{ $od_list_key }}</td>
            <td class="text-right">{{ $od_list_value }}</td>
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
            <th>{{ key($data['od']['total']) }}</th>
            <th class="text-right">{{ $data['od']['total'][key($data['od']['total'])] }}</th>
          </tr>
        </table>
      </td>
      <td class="border-top-0">
        <table class="table border mb-1">
          <tr>
            <th colspan="2" class="text-center">{{ $data['tp']['title'] }}</th>
          </tr>
          @foreach($data['tp']['list'] as $tp_list_key => $tp_list_value)
          <tr>
            <td>{{ $tp_list_key }}</td>
            <td class="text-right">{{ $tp_list_value }}</td>
          </tr>
          @endforeach
          <tr>
            <th>{{ key($data['tp']['total']) }}</th>
            <th class="text-right">{{ $data['tp']['total'][key($data['tp']['total'])] }}</th>
          </tr>
        </table>
      </td>
    </tr>

    <tr>
      <td class="border-top-0">
        <table class="table border mb-1">
          <tr>
            <th colspan="2" class="text-center">{{ $data['discount']['title'] }}</th>
          </tr>
          @foreach($data['discount']['list'] as $tp_list_key => $tp_list_value)
          <tr>
            <td>{{ $tp_list_key }}</td>
            <td class="text-right">{{ $tp_list_value }}</td>
          </tr>
          @endforeach
          <tr>
            <th>{{ key($data['discount']['total']) }}</th>
            <th class="text-right">{{ $data['discount']['total'][key($data['discount']['total'])] }}</th>
          </tr>
        </table>
      </td>
      <td class="border-top-0">
        <table class="table border mb-1">
          <tr>
            <th colspan="2" class="text-center">{{ $data['addon']['title'] }}</th>
          </tr>
          @foreach($data['addon']['list'] as $tp_list_key => $tp_list_value)
          <tr>
            <td>{{ $tp_list_key }}</td>
            <td class="text-right">{{ $tp_list_value }}</td>
          </tr>
          @endforeach
          <tr>
            <th>{{ key($data['addon']['total']) }}</th>
            <th class="text-right">{{ $data['addon']['total'][key($data['addon']['total'])] }}</th>
          </tr>
        </table>
      </td>
    </tr>

    <tr>
      <td colspan="2" class="border-top-0">
        <table class="table border mb-1">
          @foreach($data['total'] as $key => $value)
          <tr>
            <th>{{ $key }}</th>
            <th class="text-right">{{ $value }}</th>
          </tr>
          @endforeach
        </table>
      </td>
    </tr>
  </table>
</body>

</html>