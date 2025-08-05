<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <title>Link Expired</title>
</head>

<body >
    @if($password_updated == '1')
        <div class="d-flex flex-column justify-content-center align-items-center vh-100">
            {{-- <img class="h-75" src="{{file_url('images\expire_1.jpg')}}" alt="expired" loading='lazy'> --}}
            <h1 class="d-flex justify-content-center align-items-center text-danger-500" style="color: #3698DC" >Your password has already been updated using this link..!</h1>
            <p class="d-flex justify-content-center align-items-center text-muted" style="color: #3e4246">This URL is not valid anymore.</p>
        </div>
    @else
        <div class="d-flex flex-column justify-content-center align-items-center vh-100">
            {{-- <img class="h-75" src="{{file_url('images\expire_1.jpg')}}" alt="expired" loading='lazy'> --}}
            <h1 class="d-flex justify-content-center align-items-center text-danger-500" style="color: #3698DC" >Oops, this link is expired..!</h1>
            <p class="d-flex justify-content-center align-items-center text-muted" style="color: #3e4246">This URL is not valid anymore.</p>
        </div>
    @endif
</body>
<script src="{{ asset('js/bootstrap.bundle.min.js') }}"
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous">
</script>

</html>
