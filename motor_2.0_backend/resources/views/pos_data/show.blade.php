<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta htpos-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Data Details </title>
    <style>
        .btn {
            background-color: #007bff;
            /* Green */
            border-color: #007bff;
            color: white;
            padding: .375rem .75rem;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
            position: absolute;
            right: 20px;
            top: 20px;
        }
        .wrap{
            overflow-wrap: break-word;
        }
    </style>
</head>

<body>
 
    <p><b>Company Name :</b> {{ $table ?? '' }}</p>

    <p><b>IC Mapping ID :</b> {{ $table_data->ic_mapping_id ?? ''}}</p>

    <p><b>Agent ID :</b> {{ $table_data->agent_id }}</p>

    <p class="wrap"><b>Request :</b> {{ json_encode($table_data->request, JSON_PRETTY_PRINT) ?? '' }}</p>
    
    <p class="wrap"><b>Response :</b> {{ json_encode($table_data->response, JSON_PRETTY_PRINT) ?? '' }}</p>

    <p><b>Status :</b> {{ json_encode($table_data->status) ?? '' }}</p> 

</body>

</html>