<h5>Customize Quick Link</h5>
<ul class="ulink-cus-v1">
    @foreach($get_quick_link_data as $menu)
        <li class="link-cus-v1">
            <a class="btn-cus-v3" href="{{ env('APP_URL') . $menu->menu_url }}">{{ $menu->menu_name }}</a>
        </li>
    @endforeach
</ul>