<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>PDF</title>
    {{-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet"> --}}
</head>

<body>
    <table class="table-bordered w-50 table">
        <tr>
            <th>#</th>
            <th>Table Name</th>
            <th>Column Name</th>
            <th>Data Type</th>
            <th>Size</th>
        </tr>
        @php
            $table_list = DB::select('SHOW TABLES;');
            $repfix = 'Tables_in_' . config('database.connections.' . config('database.default'))['database'];
            $response = [];
            $i = 0;
        @endphp

        @foreach ($table_list as $key => $table)
            @php
                $data = DB::select("DESC {$table->$repfix};");
            @endphp

            @foreach ($data as $key => $value)
                <tr>
                    <td>{{ ++$i }}</td>
                    <td>{{ $table->$repfix }}</td>
                    <td>{{ $value->Field }}</td>
                    <td>{{ substr($value->Type, 0, 50) }}</td>
                    <td>{{ \Illuminate\Support\Facades\DB::select("SELECT
                                                                                MAX(LENGTH(`{$value->Field}`)) as max_legnth FROM `{$table->$repfix}`;")[0]->max_legnth }}
                    </td>
                </tr>
                {{-- $response[$table->$repfix][$value->Field] = ["type" => $value->Type, "max_size" => DB::select("SELECT
                MAX(LENGTH(`{$value->Field}`)) as max_legnth FROM `{$table->$repfix}`;")[0]->max_legnth]; --}}
            @endforeach
        @endforeach
    </table>
<ul>
    @foreach (\Illuminate\Support\Facades\DB::getQueryLog() as $key => $sql)
        @php
            $addSlashes = str_replace('?', "'?'", $sql['query']);
            $query = vsprintf(str_replace('?', '%s', $addSlashes), $sql['bindings'] ?? []) . ' - ' . $sql['time'] . ' ms';
        @endphp
        <li>{{ $query }}</li>
    @endforeach
</ul>
</body>

</html>
