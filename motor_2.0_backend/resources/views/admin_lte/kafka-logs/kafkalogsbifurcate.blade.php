<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<style>
    table, th, td {
      border:1px solid black;
    }
    </style>
<body>
    <table>
  <tr>
       <th>EnquiryID</th>
       <th>Old Date </th>
       <th>Old Log</th>
       <th>New Date</th>
       <th>New Logs</th>
    </tr>


    @foreach ($records as $id => $item)
    <tr>
        <td>{{$id}}</td>
        <td>{{$item["realtime"][0]->created_on ?? null}}</td> 
        <td>{{json_encode(json_decode($item["realtime"][0]->request,true),JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )}}</td>
        <td>{{!empty($item["manual"]) ? $item["manual"]->created_on : null}}</td> 

        <td>{{json_encode(json_decode(!empty($item["manual"]) ? $item["manual"]->request : null,true),JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )}}</td>
    </tr>
@endforeach

    </table>
</body>
</html>