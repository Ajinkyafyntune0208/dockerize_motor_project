

<html>
<body>
<form action="{{ url('downloadKafkaDetailsData')}}" method="post">
    @csrf
    @method('post')
    <table>
        <tr>
            <td>Enquiry ID</td>
            <td><textarea type="text" name="name" value="" style="height: 200px ;width:500px"></textarea></td>
        </tr>
    </table>
    <input type="submit" />
</form>
</body>
</html>
