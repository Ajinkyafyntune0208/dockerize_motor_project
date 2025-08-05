<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brokerage log</title>
</head>
<body>
    <b>Enquiry ID :</b>     {{customEncrypt($report->user_product_journey_id)}} 
    <br><br>
    <b>retrospective config id :</b>      {{$report->retrospective_conf_id}} 
    <br><br>
    <b>old config id :</b>      {{$report->old_conf_id}}
    <br><br>
    <b>new config id :</b>      {{$report->new_conf_id}}
    <br><br>
    <b>created at :</b>      {{$report->created_at}}
    <br><br>
    <b>updated at :</b>      {{$report->updated_at}}
    <br><br>
    <b>old config :</b> <br>     {{json_encode($report->old_config)}}
    <br><br>
    <b>new config :</b> <br>     {{json_encode($report->new_config)}}
    
</body>
</html>