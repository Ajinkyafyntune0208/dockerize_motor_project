<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OLA Share Quote Pdf</title>
    <style>
        @media(max-width: 767px) {
            .top_table {
                width: 100% !important;
            }

            .idv_value {
                padding: 0px 10px;
            }

            .help_tag {
                width: 100% !important;
                padding: 0px 10px;
            }
        }
    </style>
</head>

<body>
    <table style="margin-top: 50px; width: 100%;">
        @foreach($data as $key => $value)
        <tr>
            <td>
                <table class="top_table" style="padding: 10px 5px; font-size: 0.8rem; border: 1px solid black;font-family: sans-serif; width: 95%;border-radius: 8px;text-align: left;margin: auto;">
                    <tr>
                        <td style="width: 120px">
                            <img src="{{ $value['logo'] }}" style="height: 45px; width: auto;" />
                        </td>
                        <td style="font-weight: bold; width: 160px">{{ $value['name'] }} <small>({{ $value['productName'] }})</small></td>
                        <td class="idv_value">
                            IDV: <br><strong style="padding-top: 2px;">Rs {{ $value['idv'] }}</strong>
                        </td>
                        {{--<td>
                            GST: <br><strong style="padding-top: 2px;">Rs {{ $value['gst'] }}</strong>
                        </td>--}}
                        <td>
                            <!-- Net  -->Premium With GST: <br><strong style="padding-top: 2px;">Rs{{ $value['premiumWithGst'] }}</strong>
                        </td>
                        <td style="text-align: right;">
                            <a href="{{ $value['action'] }}" style="padding: 8px 11px;cursor: pointer; background-color: #FFC107; border-radius: 0.25rem; border: 1px solid transparent; display: inline-block; text-decoration: none; color: #212121;">Buy Now</a>
                        </td>
                </table>
            </td>
        </tr>
        @endforeach
    </table>
    <p style="text-align: center;font-size: 0.75rem;font-family: sans-serif;color: red;margin-bottom: 20px;">*Above mentioned quotes are including of GST</p>
    <p class="help_tag" style="font-size: 0.8rem;font-family: sans-serif;color: grey;width: 80%;margin: auto;">To buy from any one of the them, please click on the proceed button.
        For any assistance, please feel free to connect with our customer care at our toll free number {{ config('constants.brokerConstant.tollfree_number')}} or drop an e-mail at {{ config('constants.brokerConstant.support_email') }}</p>
</body>

</html>
