<nav>
    <div class="nav nav-tabs" id="nav-tab" role="tablist">
        <a class="nav-link {{request()->routeIs('admin.pg-config.home') ? 'active' : ''}}" href="{{route('admin.pg-config.home')}}">Configuration Setting</a>
        <a class="nav-link {{request()->routeIs('admin.pg-config.global-config') ? 'active' : ''}}" href="{{route('admin.pg-config.global-config')}}">Global Configuration</a>
        <a class="nav-link {{request()->routeIs('admin.pg-config.ic-wise-config') ? 'active' : ''}}" href="{{route('admin.pg-config.ic-wise-config')}}">IC wise Configuration</a>
    </div>
</nav>