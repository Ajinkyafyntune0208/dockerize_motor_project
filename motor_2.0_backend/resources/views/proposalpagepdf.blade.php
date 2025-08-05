<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta content="text/html; charset=utf-8" http-equiv="Content-Type">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  {{-- domPDF supports Boostrap 3 but not JS   --}}
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">

  <style>
@import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap');
</style>

  <style>
    * {
      font-family: Roboto;
    }
    .card{
      width: 100%;
      margin: 20px 0px;
      margin-left: -20px !important;
      padding: 20px;
      border: 1px solid grey;
      border-radius:10px;
      word-wrap: break-word !important;
    }
    .font{
      font-family: Roboto;
      font-weight: bold !important;  
    }
    .shadow {
        box-shadow: rgba(0, 0, 0, 0.24) 0px 3px 8px;
    }
    .mt-0{
        margin-top:0 !important; 
    }
    .mb-0{
      margin-bottom: 0 !important;
    }
    .mb-1, .my-1 {
        margin-bottom: 0.25rem !important;
    }
    .mb-3, .my-3 {
        margin-bottom: 0.75rem !important;
    }
    .mb-4, .my-4 {
        margin-bottom: 1.5rem !important;
    }
    .mt-2{
        margin-top: 0.50rem !important;
    }
    .mt-4, .my-4 {
        margin-top: 1.5rem !important;
    }
    .cus-label{
      max-width:220px;
      font-size: 14px;
      font-weight: 500;
      word-wrap: break-word;
    }
    .cus-val{
      max-width:150px !important;
      word-wrap: break-word;      
    }
    .logo {
      width: 150px;
      height: 150px;
    }
    .payamount {
      font-size: 14px;
    }
    p {
      font-size: 10px;
    }
    .title {
      font-size: 12.5px;
      font-weight: 600;
      margin: -5px -10px 10px -10px; 
      padding: 10px 5px;
      border-radius: 5px;
    }
    .payable {
      border: 1px dotted #f27f21;
    }
    @page {
      margin: 0px 0px;
    }
    body {
      margin-top: 70px;
      margin-left: 50px;
      margin-right: 50px;
      margin-bottom: 40px;
    }

    /** Define the header rules **/
    header {
      position: fixed;
      top: 0cm;
      left: 0cm;
      right: 0cm;
      height: 2cm;
    }
    footer{
      font-weight: bold;
      /* position: absolute;*/
      position: fixed !important; 
      left: 0;
      bottom: 25px;
      width: 100%;
      text-align: center;
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
          font-family: 'Roboto';
          src: url('{{ url('fonts/Roboto/Roboto-Medium.ttf') }}') format("truetype");
          font-weight: bold;
          font-style: bold;
          font-variant: bold;
      }

      *{
        font-family: "Roboto" !important; 
        font-weight: bold !important;  
      }
      .rupee-sign{
        font-family: Roboto !important;
      }
      /* h1, h2, h3, h4, h5, h6, b, strong,{
        font-family: "Roboto" !important; 
        font-weight: bold;  
      } */

      p, span, a {
        font-family: Roboto !important; 
        font-weight: normal !important;
      }
    </style>  
  @endif
  
</head>

<body>
  @php 
      function fil($string)
    {
      $words = explode('_', $string);
      $titleCaseWords = array_map('ucfirst', $words);
      return implode(' ', $titleCaseWords);
    }
      function filupper($string)
    {
      $words = explode('_', $string);
      $titleCaseWords = array_map('strtoupper', $words);
      return implode(' ', $titleCaseWords);
    }

    $keysToExclude = [
      "prev_owner_type",
      "city_id",
      "rto_location",
      "state_id",
      'address_line_1',
      'address_line_2',
      'address_line_3',
      'relationship_with_owner',
      'gender_name',
      'is_ckyc_present',
      'is_ckyc_details_rejected',
      'prevPolicyExpiryDate',
      'previousInsuranceCompany',
      'occupation',
      'first_name',
      'last_name',
      'reg_no_1',
      'reg_no_2',
      'reg_no_3',
    ];

    //Recursive function to exclude keys mentioned in above variable dynamically from the array $data
    function filterArray(&$data, $exclude) { 
      foreach ($data as $key => &$value) {
          if (is_array($value)) {
            filterArray($value, $exclude);
          } elseif (in_array($key, $exclude)) {
            unset($data[$key]);
          }
      }
    }
    filterArray($data,$keysToExclude);
  @endphp
  {{-- {{dd($data)}} --}}
  @if (isset($data))    
    @foreach ($data as $key => $value)
      @if ($key == 'general_information')
        <header>
          <div class="row" style="margin-top:10px; margin-left: 30px;">
            <div class="col-xs-4">
              <div style="font-size:14px; font-weight:bold; text-align: left;">PROPOSAL</div>  
            </div>
            <div class="col-xs-6" style="word-wrap: break-word;">
              <div style="font-size:10px; text-align: right;"><span style="font-weight: bold; text-decoration: underline;">Trace ID:</span> 
                {{$enquiryId ?? '-'}}
              </div>  
            </div>
          </div>
        </header>

        <div class="card"  style="margin-top: -10px !important;">
          <div class="row mb-0">
            <div class="col-xs-3">
                <img src="{{$data[$key]['ic_logo'] ?? '#'}}" alt="logo" class="logo"/>
            </div>
            <div class="col-xs-3 text-wrap">
                <h5>{{$data[$key]['insurer_company'] ?? '-'}}</h5>
                <p>{{$data[$key]['insurer_type'] ?? '-'}}</p>     
            </div>
            <div class="col-xs-2 text-wrap" >
                <h5>Plan type & Policy type</h5>
                <p class="cus-val">{{ $data[$key]['plan_and_policy_type'] ?? '-' }}</p>  
            </div>
            <div class="col-xs-4 text-wrap" >
                <h5 style="margin-bottom: 2rem;"> IDV Value</h5>
                <p class="rupee-sign">{{$data[$key]['idv'] ?? '-'}}</p> 
            </div>
          </div>
          <hr>
          @if(isset($value['vehicle_details']) && (is_array($value['vehicle_details']) && (count($value['vehicle_details']) > 0 )))
      
            <h4 style="font-size:12.5px !important; text-decoration: underline;">Vehicle Details</h4>
            <div class="row">
                <div class="col-xs-3">
                    <p class="cus-label">Manufacturer Name</p>
                    <p>{{$value['vehicle_details']['manf_name'] ?? '-'}}</p>
                </div>
                <div class="col-xs-3">
                    <p class="cus-label">Model Name</p>
                    <p>{{$value['vehicle_details']['model_name'] ?? '-'}}</p>
                </div>
                <div class="col-xs-3">
                  <p class="cus-label">Variant</p>
                  <p>{{$value['vehicle_details']['variant'] ?? '-'}}</p>
                </div>
                <div class="col-xs-3">
                  <p class="cus-label">Fuel Type</p>
                  <p>{{$value['vehicle_details']['fuel_type'] ?? '-'}}</p>
                </div>
            </div>
          @endif
          
          @if (isset($value['selected_addons']) && (is_array($value['selected_addons']) && (count($value['selected_addons']) > 0 )))
            <h4 style="font-size: 12.5px !important; text-decoration: underline;">
                Selected Addons
            </h4>
            @php displayItems($value['selected_addons']); @endphp
          @endif
          @if (isset($value['additional_covers']) && (is_array($value['additional_covers']) && (count($value['additional_covers']) > 0 )))
            <h4 style="font-size: 12.5px !important; text-decoration: underline;">
                Additional Covers
            </h4>
            @php displayItems($value['additional_covers']); @endphp
          @endif

          @if(isset($value['premium_breakup']) && (is_array($value['premium_breakup']) && (count($value['premium_breakup']) > 0 )))

            <h4 style=" font-size:12.5px !important; text-decoration: underline;">Premium Break-up</h4>
            <div class="row">
                <div class="col-xs-4 ">
                    <p class="cus-label">Own Damage Premium</p>
                    <p class="rupee-sign">{{$value['premium_breakup']['own_damage_premium'] ?? '-'}}</p>
                </div>
                <div class="col-xs-4">
                    <p class="cus-label">Third Party Premium</p>
                    <p class="rupee-sign">{{$value['premium_breakup']['third_party_premium'] ?? '-'}}</p>
                </div>
                <div class="col-xs-4">
                    <p class="cus-label">Addon Premium</p>
                    <p class="cus-val rupee-sign">{{$value['premium_breakup']['addon_premium'] ?? '-'}}</p>
                </div>
            </div>
            <div class="row">
                <div class="col-xs-4">
                    <p class="cus-label">Total Discount {{$value['premium_breakup']['total_discount']['ncb_include'] ?? '-'}}</p>
                    <p class="rupee-sign">{{$value['premium_breakup']['total_discount']['final_discount'] ?? '-'}}</p>
                </div>
                <div class="col-xs-4">
                    <p class="cus-label">GST</p>
                    <p class="rupee-sign">{{$value['premium_breakup']['gst'] ?? '-'}}</p>
                </div>
            </div>
            <div style="padding: 6px; padding-bottom: 0;">
                <div class="payable row">
                    <div class="col-xs-6" >
                      <p style="font-size: 22px; font-weight: 400;" >Total Premium Payable</p>
                    </div>
                    <div class="col-xs-5" style="text-align: right;">
                      <p class="rupee-sign" style="font-size: 22px !important; font-weight: 700;">{{$value['premium_breakup']['total_premium_payable'] ?? '-'}}</p>
                    </div>
                </div>
                <br>
            </div>
          @endif

        </div>

      @endif
    
      @if ($key != 'general_information')
      <div class="card">
          <div class="font" class="title mb-3" style="background: {{$broker_theme_color }};">
            {{filupper($key)}}
          </div>
          @php displayItems($value); @endphp
      </div>
      @endif

    @endforeach

  @endif
  <footer>
    <div style="text-align: right; font-size:12px; font-weight: bold; margin-right: 30px;">Date & Time:
      {{Carbon\Carbon::now()->format('d/m/y H:i:s')}}
    </div>
  </footer>
  <script type="text/php">
    if (isset($pdf)) {
      $pdf->page_script('
          $text = __("Page :pageNum/:pageCount", ["pageNum" => $PAGE_NUM, "pageCount" => $PAGE_COUNT]);
          $font = null;
          $size = 9;
          $color =  isset($broker_text_color) ? $broker_text_color : array(0, 0, 0);
          $word_space = 0.0;  //  default
          $char_space = 0.0;  //  default
          $angle = 0.0;   //  default

          // Compute text width to center correctly
          $textWidth = $fontMetrics->getTextWidth($text, $font, $size);

          $x = ($pdf->get_width() - $textWidth) / 2;
          $y = $pdf->get_height() - 35;

          $pdf->text($x, $y, $text, $font, $size, $color, $word_space, $char_space, $angle);
      '); // End of page_script
    }
  </script>

</body>

</html>

@php
  // function displayItems($items) {
  //   $i = 0;

  //   foreach ($items as $key => $value) {
  //     if (is_array($value)  && !empty($value)) {

  //       echo '</div>'; // Close the previous row
  //       echo '<div class="row">';

  //     } else {

  //       if ($i % 3 == 0) {
  //         if ($i > 0) {
  //           echo '</div>'; // Close the previous row
  //         }
  //         echo '<div class="row">'; // Start a new row
  //       }

  //     }

  //     if (is_array($value) && !empty($value)) {

  //       echo '<div class="row">'; 
  //       echo '</div>'; 
  //       echo '<p style="margin-left: 10px; font-size:18px; font-weight:bold; text-decoration: underline;">'.(filupper($key)).'</p>';
  //       echo '</div>';                                
  //       displayItems($value);

  //     } else { 
  //   
  //       echo '<div class="col-xs-4">';
  //       echo '<p class="cus-label">' . (filupper($key)) . '</p>';
  //       // echo '<p class="cus-val" >' . $value . '</p>'; //default 
  //       echo '<p class="cus-val"'. (str_contains($key,'address') ? ' style="max-width: 250px !important;"' : '') . '>' . (!empty($value) ? fil($value) ?? '-' : '-')  . '</p>';

  //     }

  //     echo '</div>'; 
  //     $i++;

  //   }
    
  //   echo '</div>'; 
  // }
  function displayItems($items) {
    $i = 0;
    $colCounter = 0;

    echo '<div class="row">'; // Start the first row
    foreach ($items as $key => $value) {
        // Check if the value is an array
        if (is_array($value) && !empty($value)) {
            // Display a heading for the nested array
            echo '</div>'; // Close the current row
            echo '<div class="row">';
            echo '<h4 style="margin-left: 10px; font-size: 12.5px !important; font-weight: bold; text-decoration: underline;">' . filupper($key) . '</h4>';
            echo '</div>';
            // Recursively display the nested array
            displayItems($value);
            // Start a new row for subsequent items
            echo '<div class="row">';
            $colCounter = 0; // Reset column counter after processing the nested array
        } else {
            // Check if a new row needs to be started (every 3 items)
            if ($i % 3 == 0 && $i > 0) {
                echo '</div>'; // Close the current row
                echo '<div class="row">'; // Start a new row
                $colCounter = 0; // Reset column counter
            }
            // Display the key-value pair in a column
            echo '<div class="col-xs-4">';
            echo '<p class="cus-label">' . (($key == 'reg_no_1') ? 'Registered RTO' : fil($key)) . '</p>';
            echo '<p class="cus-val"' . (str_contains($key, 'address') ? ' style="max-width: 250px !important;"' : '') . '>';
            echo (!empty($value) ? fil($value) ?? '-' : '-') . '</p>';
            echo '</div>';
            $colCounter++; // Increment column counter
        }
        $i++; // Increment item counter
    }
    echo '</div>'; // Close the last row
}
@endphp