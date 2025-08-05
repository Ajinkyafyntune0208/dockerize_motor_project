<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    <table>
        @foreach ($data as $key => $value)
            @if ($key == 0)
                <tr>
                    @foreach ($value as $key1 => $item)
                        <th>{{ $item }}</th>
                    @endforeach
                </tr>
            @else
                <tr>
                    @foreach ($value as $key2 => $item)
                            <td>{{ $item }}</td>
                    @endforeach
                </tr>
            @endif
        @endforeach
    </table>
</body>

</html>