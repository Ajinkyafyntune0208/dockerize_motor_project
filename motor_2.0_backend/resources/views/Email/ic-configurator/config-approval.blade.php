<h2>Hello {{ $user_details['name'] }}</h2>
<p> I am writing this email to request your approval for new changes in IC Configurator.</p>
Thanks,<br>
{{ ucfirst(auth()->user()->name) }}